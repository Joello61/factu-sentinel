<?php

declare(strict_types=1);

namespace App\Document\Entity;

use App\Document\Enum\DocumentProcessingFailureReason;
use App\Document\Enum\DocumentProcessingStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Une tentative de traitement d'un Document (docs/07-data-model.md, section 14). Plusieurs
 * DocumentProcessingRecord peuvent exister pour un même Document au fil du temps (modèle
 * historique, plan Phase 7) : Document::$currentProcessingRecord pointe toujours vers la
 * tentative la plus récente, les précédentes restent en base, jamais supprimées ni modifiées
 * une fois dans un état terminal (PARSED, VALIDATED, FAILED).
 *
 * Cette phase ne crée jamais qu'une seule tentative par Document, à l'upload initial - aucun
 * endpoint/bouton de réessai n'existe (plan Phase 7, hors périmètre explicite). Le modèle
 * multi-tentatives est une décision d'architecture pour l'asynchrone (extensibilité future),
 * pas l'implémentation d'une fonctionnalité de réessai dès cette phase.
 *
 * PARSED est une valeur légitime du modèle de données mais n'est produite par aucun chemin
 * de code de cette phase (comme DocumentFileFormat::INCONNU) : App\Document\MessageHandler\
 * ExtractDocumentContentHandler écrit toujours directement VALIDATED ou FAILED comme état
 * terminal, jamais PARSED comme état persisté intermédiaire (extraction et validation
 * Mustang se font toutes deux avant la seule écriture finale, pas de valeur ajoutée à
 * persister un état intermédiaire pour une séquence de quelques secondes).
 *
 * Pas de TenantScopedInterface (comme ComplianceFinding vis-à-vis de ComplianceAnalysis) :
 * le scoping tenant passe exclusivement par Document, jamais interrogé directement par
 * organization_id.
 */
#[ORM\Entity]
#[ORM\Table(name: 'document_processing_records')]
#[ORM\Index(name: 'idx_document_processing_records_document_id', columns: ['document_id'])]
class DocumentProcessingRecord
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(name: 'document_id', nullable: false)]
    private Document $document;

    #[ORM\Column(type: Types::STRING, enumType: DocumentProcessingStatus::class)]
    private DocumentProcessingStatus $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::STRING, enumType: DocumentProcessingFailureReason::class, nullable: true)]
    private ?DocumentProcessingFailureReason $failureReason = null;

    /**
     * Pont technique vers l'extraction, jamais une source de vérité métier (plan Phase 7,
     * invariant central) : jamais lu par App\Compliance\Engine\Service\ComplianceEngine, ni
     * écrit automatiquement dans Invoice/InvoiceLine/Customer. Toujours null pour un Document
     * PDF_SIMPLE (décision produit 3, plan Phase 7 - aucune extraction de champs tentée sur
     * un PDF non structuré).
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $extractedDataSummary = null;

    public function __construct(Document $document)
    {
        $this->id = Uuid::v7();
        $this->document = $document;
        $this->status = DocumentProcessingStatus::UPLOADED;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function getStatus(): DocumentProcessingStatus
    {
        return $this->status;
    }

    public function getFailureReason(): ?DocumentProcessingFailureReason
    {
        return $this->failureReason;
    }

    /** @return array<string, mixed>|null */
    public function getExtractedDataSummary(): ?array
    {
        return $this->extractedDataSummary;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    /**
     * Réclame cette tentative pour traitement (UPLOADED -> PROCESSING). Auto-gardé (comme
     * App\Invoicing\Entity\Invoice::markStale()) : no-op si déjà PROCESSING ou dans un état
     * terminal - c'est ce garde-fou, combiné au verrou pessimiste posé par l'appelant
     * (App\Document\MessageHandler\ExtractDocumentContentHandler), qui matérialise
     * l'invariant d'idempotence d'une redélivrance Messenger (plan Phase 7).
     *
     * @return bool true si la transition a eu lieu, false si no-op (déjà réclamée/terminale)
     */
    public function claim(): bool
    {
        if (DocumentProcessingStatus::UPLOADED !== $this->status) {
            return false;
        }

        $this->status = DocumentProcessingStatus::PROCESSING;
        $this->startedAt = new \DateTimeImmutable();

        return true;
    }

    /** @param array<string, mixed>|null $extractedDataSummary */
    public function markValidated(?array $extractedDataSummary): void
    {
        $this->status = DocumentProcessingStatus::VALIDATED;
        $this->extractedDataSummary = $extractedDataSummary;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function markFailed(DocumentProcessingFailureReason $reason): void
    {
        $this->status = DocumentProcessingStatus::FAILED;
        $this->failureReason = $reason;
        $this->completedAt = new \DateTimeImmutable();
    }

    /**
     * DELETE /documents/{id} (docs/07-data-model.md, section 30) : les données extraites
     * contiennent par nature des données personnelles/sensibles (client, montants) dès
     * qu'elles existent (toujours null sauf pour un Document FACTURX déjà VALIDATED) -
     * suppression inconditionnelle, jamais une suppression conditionnelle au cas par cas.
     */
    public function clearExtractedDataSummary(): void
    {
        $this->extractedDataSummary = null;
    }
}
