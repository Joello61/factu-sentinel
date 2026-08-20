<?php

declare(strict_types=1);

namespace App\Document\MessageHandler;

use App\Document\Entity\Document;
use App\Document\Entity\DocumentProcessingRecord;
use App\Document\Enum\DocumentFileFormat;
use App\Document\Enum\DocumentProcessingFailureReason;
use App\Document\Message\ExtractDocumentContentMessage;
use App\Document\Repository\DocumentProcessingRecordRepository;
use App\Document\Repository\DocumentRepository;
use App\Document\Service\FacturXDataExtractor;
use App\Document\Service\MustangExtractionStatus;
use App\Document\Service\StructuredDocumentValidatorInterface;
use App\Shared\Doctrine\TenantFilter;
use App\Shared\Storage\StorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Traite un document importé (docs/06-technical-architecture.md, section 11-13 ; plan Phase
 * 7). Jamais appelé directement, uniquement via le bus Messenger (config/packages/
 * messenger.yaml, transport "async").
 *
 * INVARIANT ARCHITECTURAL NON NÉGOCIABLE (plan Phase 7) : ce handler ne crée jamais
 * d'Invoice ni de Customer, et n'écrit jamais directement dans Invoice/InvoiceLine/Customer.
 * DocumentProcessingRecord::extractedDataSummary reste un pont de suggestion, consommé
 * uniquement par le frontend (Invoice Editor) après confirmation explicite de l'utilisateur
 * - jamais une source lue par App\Compliance\Engine\Service\ComplianceEngine. Toute
 * modification future de ce handler qui romprait cette frontière doit être explicitement
 * discutée, jamais glissée incidemment.
 *
 * Isolation tenant (backend/CLAUDE.md, section 9-10) : aucun kernel.request/JWT n'existe
 * dans un worker Messenger - App\Shared\Security\TenantFilterActivationListener (qui active
 * TenantFilter pour une requête HTTP) ne s'exécute donc jamais ici. Ce handler active
 * lui-même TenantFilter avec ExtractDocumentContentMessage::organizationId avant toute
 * requête Doctrine, reproduisant exactement la même logique - un message qui « oublierait »
 * son tenant serait sinon une brèche potentielle (accès cross-tenant sans filtre actif).
 */
#[AsMessageHandler]
final class ExtractDocumentContentHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DocumentRepository $documentRepository,
        private readonly DocumentProcessingRecordRepository $processingRecordRepository,
        private readonly StructuredDocumentValidatorInterface $structuredDocumentValidator,
        private readonly StorageInterface $storage,
        private readonly FacturXDataExtractor $facturXDataExtractor,
    ) {
    }

    public function __invoke(ExtractDocumentContentMessage $message): void
    {
        $this->activateTenantFilter($message->organizationId);

        // Phase A (transaction courte, verrou pessimiste) : réclame la tentative avant tout
        // traitement long. Voir App\Document\Entity\DocumentProcessingRecord::claim() et
        // App\Document\Repository\DocumentProcessingRecordRepository::findForUpdate() -
        // c'est ce verrou, jamais maintenu pendant l'appel HTTP à Mustang (phase B,
        // potentiellement plusieurs secondes), qui matérialise l'invariant d'idempotence
        // (plan Phase 7) sans bloquer d'autres workers pendant l'I/O externe.
        $claimed = $this->entityManager->wrapInTransaction(function () use ($message): bool {
            $record = $this->processingRecordRepository->findForUpdate($message->documentProcessingRecordId);

            return null !== $record && $record->claim();
        });

        if (!$claimed) {
            // No-op : redélivrance Messenger d'un message déjà traité, ou tentative déjà
            // terminale. Jamais une erreur.
            return;
        }

        $document = $this->documentRepository->find($message->documentId);
        if (null === $document) {
            // Ne devrait jamais arriver (le message n'est dispatché qu'après le commit de la
            // création du Document, App\Document\Service\UploadDocumentService) - défense en
            // profondeur : le message est laissé en échec technique pour retry Messenger
            // plutôt que de faire planter silencieusement le worker.
            throw new \RuntimeException(\sprintf('Document "%s" not found.', $message->documentId->toRfc4122()));
        }

        [$fileFormat, $extractedDataSummary, $failureReason] = $this->process($document);

        // Phase C (nouvelle transaction courte) : plus besoin de verrou ici, cette tentative
        // a déjà été exclusivement réclamée en phase A - aucun autre worker ne peut détenir
        // la même tentative en parallèle.
        $this->entityManager->wrapInTransaction(function () use ($message, $fileFormat, $extractedDataSummary, $failureReason): void {
            $document = $this->documentRepository->find($message->documentId);
            $record = $this->processingRecordRepository->find($message->documentProcessingRecordId);
            \assert(null !== $document && null !== $record);

            if (null !== $fileFormat) {
                $document->classify($fileFormat);
            }

            if (null !== $failureReason) {
                $record->markFailed($failureReason);
            } else {
                $record->markValidated($extractedDataSummary);
            }
        });
    }

    /**
     * @return array{0: ?DocumentFileFormat, 1: ?array<string, string>, 2: ?DocumentProcessingFailureReason}
     */
    private function process(Document $document): array
    {
        try {
            $content = $this->storage->retrieve($document->getStorageReference());
        } catch (\RuntimeException) {
            return [null, null, DocumentProcessingFailureReason::INVALID_DOCUMENT];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedType = $finfo->buffer($content);

        if ('application/pdf' === $detectedType) {
            return $this->processPdf($content);
        }

        if (\in_array($detectedType, ['application/xml', 'text/xml'], true)) {
            return $this->processXml($content);
        }

        // Ne devrait jamais arriver : App\Document\Service\UploadedDocumentValidator
        // n'accepte que PDF/XML à l'upload. Défense en profondeur uniquement.
        return [null, null, DocumentProcessingFailureReason::INVALID_DOCUMENT];
    }

    /**
     * @return array{0: ?DocumentFileFormat, 1: ?array<string, string>, 2: ?DocumentProcessingFailureReason}
     */
    private function processPdf(string $content): array
    {
        $extraction = $this->structuredDocumentValidator->extract($content);

        if (MustangExtractionStatus::SERVICE_ERROR === $extraction->status) {
            return [null, null, DocumentProcessingFailureReason::MUSTANG_UNAVAILABLE];
        }

        if (MustangExtractionStatus::NO_XML_EMBEDDED === $extraction->status) {
            // PDF simple (décision 3, plan Phase 7) : aucune extraction de champs tentée,
            // classification seule - alimente directement la règle format-facture-electronique
            // (US-COMPLIANCE-005).
            return [DocumentFileFormat::PDF_SIMPLE, null, null];
        }

        \assert(null !== $extraction->xml);

        try {
            $this->structuredDocumentValidator->validate($content);
        } catch (\RuntimeException) {
            return [DocumentFileFormat::FACTURX, null, DocumentProcessingFailureReason::MUSTANG_VALIDATION_FAILED];
        }

        $extractedDataSummary = $this->facturXDataExtractor->extract($extraction->xml);

        return [DocumentFileFormat::FACTURX, $extractedDataSummary, null];
    }

    /**
     * @return array{0: ?DocumentFileFormat, 1: ?array<string, string>, 2: ?DocumentProcessingFailureReason}
     */
    private function processXml(string $content): array
    {
        // Sniff volontairement minimal (recherche de sous-chaîne, jamais un parsing XML -
        // décision 2, plan Phase 7 : ni UBL ni CII ne sont traités dans cette phase, cette
        // distinction n'a de valeur que pour l'affichage, pas pour un comportement différent).
        if (str_contains($content, 'urn:oasis:names:specification:ubl')) {
            return [DocumentFileFormat::UBL, null, DocumentProcessingFailureReason::FORMAT_NOT_SUPPORTED];
        }

        if (str_contains($content, 'urn:un:unece:uncefact')) {
            return [DocumentFileFormat::CII, null, DocumentProcessingFailureReason::FORMAT_NOT_SUPPORTED];
        }

        // XML reconnu comme tel à l'upload mais dont le schéma n'est ni UBL ni CII - jamais
        // classifié DocumentFileFormat::INCONNU (valeur réservée, purement défensive, voir
        // cet enum) : file_format reste simplement non déterminé (null).
        return [null, null, DocumentProcessingFailureReason::INVALID_DOCUMENT];
    }

    private function activateTenantFilter(Uuid $organizationId): void
    {
        $filter = $this->entityManager->getFilters()->enable('tenant_filter');
        \assert($filter instanceof TenantFilter);
        $filter->setParameter('organization_id', $organizationId->toRfc4122(), 'string');
    }
}
