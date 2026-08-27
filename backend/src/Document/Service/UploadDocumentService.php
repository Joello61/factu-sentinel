<?php

declare(strict_types=1);

namespace App\Document\Service;

use App\Document\Entity\Document;
use App\Document\Entity\DocumentProcessingRecord;
use App\Document\Http\DocumentView;
use App\Document\Message\ExtractDocumentContentMessage;
use App\Identity\Entity\User;
use App\Invoicing\Entity\Invoice;
use App\Invoicing\Enum\InvoiceStatus;
use App\Organization\Entity\Organization;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use App\Shared\Idempotency\Service\IdempotencyStore;
use App\Shared\Metrics\MetricsRecorder;
use App\Shared\Security\EmailVerificationGuard;
use App\Shared\Storage\StorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Uid\Uuid;

/**
 * Orchestration transactionnelle de POST /documents, même style que
 * App\Compliance\Engine\Service\RunComplianceAnalysisService.
 *
 * Décision produit Phase 7 (corrigée après revue) : un document ne peut être rattaché qu'à
 * une Invoice DRAFT ou READY_FOR_ANALYSIS, jamais ANALYZED/ANALYSIS_STALE - l'interaction
 * d'un import de document avec une facture déjà analysée est hors périmètre de cette phase.
 * Cette vérification est une règle métier, pas un détail HTTP : elle vit ici, jamais dans
 * App\Document\Controller\CreateDocumentController (backend/CLAUDE.md, section 3).
 */
final class UploadDocumentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UploadedDocumentValidator $validator,
        private readonly AntivirusScannerInterface $antivirusScanner,
        private readonly StorageInterface $storage,
        private readonly MessageBusInterface $messageBus,
        private readonly AuditLogger $auditLogger,
        private readonly IdempotencyStore $idempotencyStore,
        private readonly MetricsRecorder $metricsRecorder,
        private readonly Security $security,
        private readonly EmailVerificationGuard $emailVerificationGuard,
        #[Autowire(service: 'limiter.document_upload')]
        private readonly RateLimiterFactory $rateLimiter,
    ) {
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function upload(Organization $organization, Invoice $invoice, UploadedFile $file, string $idempotencyKey): array
    {
        $this->emailVerificationGuard->assertVerified();

        $limit = $this->rateLimiter->create($organization->getId()->toRfc4122())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        // Le message n'est dispatché qu'après le commit réel de la transaction (jamais
        // depuis l'intérieur de wrapInTransaction()) : dispatcher plus tôt risquerait qu'un
        // worker Messenger charge le Document avant que la ligne ne soit visible en base
        // (vérifié via la documentation Symfony Messenger actuelle au moment de
        // l'implémentation - problème classique de "transactional outbox", pas propre à ce
        // projet). $dispatch reste null sur le chemin de rejeu idempotent (doUpload() n'est
        // alors jamais appelée par IdempotencyStore::execute()), donc jamais de second
        // dispatch pour une même Idempotency-Key.
        //
        // La closure englobante DOIT être "function", jamais une arrow function ("fn") :
        // "fn" capture automatiquement $dispatch PAR VALEUR au moment de sa création (encore
        // null), de sorte que le "use (&$dispatch)" de la closure imbriquée ne référencerait
        // alors que cette copie locale, jamais la variable $dispatch réelle de cette méthode
        // - bug constaté à l'implémentation (Document créé, mais jamais dispatché) avant
        // cette correction.
        $dispatch = null;

        try {
            $result = $this->entityManager->wrapInTransaction(
                function () use ($organization, $invoice, $file, $idempotencyKey, &$dispatch): array {
                    return $this->idempotencyStore->execute(
                        $organization->getId(),
                        $idempotencyKey,
                        function () use ($organization, $invoice, $file, &$dispatch): array {
                            [$body, $dispatch] = $this->doUpload($organization, $invoice, $file);

                            return $body;
                        },
                    );
                },
            );
        } catch (\Throwable $exception) {
            $this->metricsRecorder->recordDocumentUpload('rejected');

            throw $exception;
        }

        if (null !== $dispatch) {
            $dispatch();
        }

        return $result;
    }

    /** @return array{0: array{status: int, body: array<string, mixed>}, 1: callable(): void} */
    private function doUpload(Organization $organization, Invoice $invoice, UploadedFile $file): array
    {
        if (!\in_array($invoice->getStatus(), [InvoiceStatus::DRAFT, InvoiceStatus::READY_FOR_ANALYSIS], true)) {
            throw new ConflictHttpException(
                'Un document ne peut être importé que sur une facture pas encore analysée.',
            );
        }

        // Validation avant tout traitement (SEC-DOC-001) : taille, magic bytes, jamais
        // l'extension ni le Content-Type déclaré.
        $validated = $this->validator->validate($file);

        $content = file_get_contents($file->getRealPath());
        \assert(false !== $content);

        // Scan avant toute persistance (Phase 17, docs/12-roadmap.md) : un contenu infecté
        // ne doit jamais atteindre StorageInterface::store(), cohérent avec le principe déjà
        // appliqué par UploadedDocumentValidator ("ne jamais persister un fichier non
        // validé"). Lève une HttpException (422 infecté, 503 scanner indisponible) qui
        // annule toute la transaction englobante, y compris la réservation d'idempotence -
        // la clé reste rejouable (App\Shared\Idempotency\Service\IdempotencyStore).
        $this->antivirusScanner->scan($content);

        $storageReference = $this->storage->store($content);

        $document = new Document(
            $organization,
            $invoice,
            $validated->sanitizedFileName,
            $validated->fileSize,
            $validated->checksum,
            $storageReference,
        );
        $processingRecord = new DocumentProcessingRecord($document);
        $document->setCurrentProcessingRecord($processingRecord);

        $this->entityManager->persist($document);
        $this->entityManager->persist($processingRecord);

        $this->auditLogger->record(
            $organization->getId(),
            ActorType::USER,
            $this->currentActorId(),
            EventType::DOCUMENT_UPLOADED,
            'Document',
            $document->getId()->toRfc4122(),
            null,
            ['invoice_id' => $invoice->getId()->toRfc4122(), 'file_name' => $validated->sanitizedFileName],
        );

        $documentId = $document->getId();
        $processingRecordId = $processingRecord->getId();
        $organizationId = $organization->getId();

        $this->metricsRecorder->recordDocumentUpload('success');

        return [
            ['status' => 202, 'body' => ['data' => DocumentView::fromEntity($document)]],
            function () use ($documentId, $processingRecordId, $organizationId): void {
                $this->messageBus->dispatch(new ExtractDocumentContentMessage(
                    $documentId,
                    $processingRecordId,
                    $organizationId,
                ));
            },
        ];
    }

    private function currentActorId(): ?Uuid
    {
        $currentUser = $this->security->getUser();

        return $currentUser instanceof User ? $currentUser->getId() : null;
    }
}
