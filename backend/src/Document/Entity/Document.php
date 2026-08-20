<?php

declare(strict_types=1);

namespace App\Document\Entity;

use App\Document\Enum\DocumentFileFormat;
use App\Document\Enum\DocumentProcessingStatus;
use App\Invoicing\Entity\Invoice;
use App\Organization\Entity\Organization;
use App\Shared\Doctrine\TenantScopedInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Fichier importé (docs/07-data-model.md, section 13). `invoice` est **non-nullable**, à la
 * différence du modèle de données générique documenté ("optionnel") : décision produit Phase
 * 7 (plan Phase 7, décision 1, corrigée après revue) - un import de document est toujours
 * rattaché à une Invoice existante appartenant à l'organisation appelante et dont le statut
 * est DRAFT ou READY_FOR_ANALYSIS (jamais ANALYZED/ANALYSIS_STALE, vérifié par
 * App\Document\Service\UploadDocumentService, pas ici) ; aucun chemin de cette phase ne crée
 * de Document sans Invoice cible.
 *
 * Le contenu binaire n'est jamais stocké ici : `storageReference` est un identifiant opaque
 * généré par App\Shared\Storage\StorageInterface (jamais dérivé de `fileName`).
 *
 * Régime de suppression mixte (docs/07-data-model.md, section 30 ; backend/CLAUDE.md,
 * section 7) : `deletedAt` marque une suppression logique des métadonnées restantes, tandis
 * que le fichier physique et les données extraites sensibles sont supprimées séparément par
 * App\Document\Service\DeleteDocumentService - cette entité ne fait jamais elle-même l'appel
 * à StorageInterface::delete().
 */
#[ORM\Entity]
#[ORM\Table(name: 'documents')]
#[ORM\Index(name: 'idx_documents_organization_id', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_documents_invoice_id', columns: ['invoice_id'])]
class Document implements TenantScopedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false)]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(name: 'invoice_id', nullable: false)]
    private Invoice $invoice;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $fileName;

    /**
     * Null tant que la classification asynchrone n'a pas encore eu lieu (voir
     * App\Document\MessageHandler\ExtractDocumentContentHandler) - jamais
     * DocumentFileFormat::INCONNU comme valeur de départ, cette valeur reste réservée au cas
     * défensif documenté sur l'enum lui-même.
     */
    #[ORM\Column(type: Types::STRING, enumType: DocumentFileFormat::class, nullable: true)]
    private ?DocumentFileFormat $fileFormat = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $fileSize;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $checksum;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $storageReference;

    /**
     * Pointeur explicite vers la tentative de traitement la plus récente (plan Phase 7,
     * modèle historique de DocumentProcessingRecord) - jamais null après construction (mis à
     * jour dans la même transaction que la création de chaque nouvelle tentative), nullable
     * uniquement pour permettre la construction en deux temps (Document puis
     * DocumentProcessingRecord qui le référence). L'intégrité "appartient toujours au même
     * Document" est garantie au niveau base de données par une clé étrangère composite
     * (voir la migration - Doctrine ne modélise ici qu'une colonne simple, la contrainte
     * réelle vit en SQL).
     */
    #[ORM\ManyToOne(targetEntity: DocumentProcessingRecord::class)]
    #[ORM\JoinColumn(name: 'current_processing_record_id', nullable: true)]
    private ?DocumentProcessingRecord $currentProcessingRecord = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $uploadedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(
        Organization $organization,
        Invoice $invoice,
        string $fileName,
        int $fileSize,
        string $checksum,
        string $storageReference,
    ) {
        $this->id = Uuid::v7();
        $this->organization = $organization;
        $this->invoice = $invoice;
        $this->fileName = $fileName;
        $this->fileSize = $fileSize;
        $this->checksum = $checksum;
        $this->storageReference = $storageReference;
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrganizationId(): Uuid
    {
        return $this->organization->getId();
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getFileFormat(): ?DocumentFileFormat
    {
        return $this->fileFormat;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function getStorageReference(): string
    {
        return $this->storageReference;
    }

    public function getCurrentProcessingRecord(): ?DocumentProcessingRecord
    {
        return $this->currentProcessingRecord;
    }

    public function setCurrentProcessingRecord(DocumentProcessingRecord $record): void
    {
        $this->currentProcessingRecord = $record;
    }

    /**
     * Toujours dérivé de la tentative courante (docs/07-data-model.md, section 13 :
     * "Document.processing_status (via DocumentProcessingRecord)") - jamais une colonne
     * dupliquée à maintenir en cohérence manuellement, pour éliminer tout risque de dérive
     * entre les deux (backend/CLAUDE.md, section 14 : pas de duplication évitable).
     */
    public function getProcessingStatus(): DocumentProcessingStatus
    {
        return $this->currentProcessingRecord?->getStatus() ?? DocumentProcessingStatus::UPLOADED;
    }

    public function classify(DocumentFileFormat $format): void
    {
        $this->fileFormat = $format;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function markDeleted(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
    }
}
