<?php

declare(strict_types=1);

namespace App\Notification\Entity;

use App\Identity\Entity\User;
use App\Notification\Enum\Channel;
use App\Notification\Enum\NotificationStatus;
use App\Notification\Enum\NotificationType;
use App\Notification\Enum\SenderType;
use App\Notification\Enum\TargetType;
use App\Notification\Repository\NotificationRepository;
use App\Organization\Entity\Organization;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * docs/07-data-model.md, section 21 : étendue en Phase 14/15 pour porter, au-delà du rappel
 * système, une notification composée par un humain. Résolution immédiate des destinataires à
 * l'envoi (App\Notification\Service\SendTeamNotificationService) : une ligne par destinataire
 * effectif, jamais une ligne "large" résolue plus tard - plus simple, et seul modèle
 * compatible avec le filtrage strict par `recipientUserId` qu'exige GET /notifications
 * (voir isolation ci-dessous).
 *
 * **Invariant non négociable** : `organizationId` seul ne suffit jamais à autoriser la
 * lecture d'une notification par un membre de l'organisation - `recipientUserId` doit
 * systématiquement filtrer en plus (App\Notification\Repository\NotificationRepository),
 * jamais à sa place. Un OWNER/ADMIN ne peut pas lire les notifications adressées à un autre
 * membre de sa propre organisation via cet entrepôt (docs/10-security-privacy.md, section
 * 16 : "Notifications - envoyées uniquement au(x) membre(s) de l'organisation concernée",
 * précisé ici : à CE membre précis, pas à l'organisation entière).
 *
 * `sourceDiagnosticId` reste une référence opaque (UUID nu, jamais une relation Doctrine
 * vers App\Compliance\Entity\EligibilityDiagnostic) : Compliance et Notification sont deux
 * modules distincts (docs/06-technical-architecture.md, section 7) qui ne partagent jamais
 * directement leurs entités internes. Champ dormant en Phase 14 (rappel système
 * `echeance_obligation`, P2, non construit cette phase).
 *
 * **Révision Phase 15** (docs/07-data-model.md, section 21 : "organization_id nullable -
 * null pour une notification émise par un PlatformAdministrator, portée cross-tenant") :
 * cette entité **cesse d'implémenter TenantScopedInterface**. Un `organization_id` NULL
 * pour une notification plateforme est incompatible avec ce marqueur (qui exige un
 * organization_id non nul, App\Shared\Doctrine\TenantScopedInterface) - le filtre Doctrine
 * (TenantFilter) exclurait par construction SQL toute ligne à organization_id NULL du
 * firewall tenant, rendant ces notifications invisibles dans l'inbox normale de leur
 * destinataire, ce qui contredirait l'objectif même de la fonctionnalité. La protection
 * automatique est donc remplacée par une règle explicite, centralisée dans
 * App\Notification\Repository\NotificationRepository : `recipientUser = :user AND
 * (organization = :currentOrg OR organization IS NULL)` - jamais déléguée au filtre
 * générique, cohérent avec le principe que l'isolation ne doit jamais reposer sur la seule
 * discipline d'un mécanisme implicite (docs/06-technical-architecture.md, section 20).
 * Exactement le même choix que App\Shared\Audit\Entity\AuditLogEntry, qui n'implémente déjà
 * pas ce marqueur pour la même raison (docs/07-data-model.md, section 25).
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
class Notification
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: true)]
    private ?Organization $organization;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recipient_user_id', nullable: false)]
    private User $recipientUser;

    #[ORM\Column(name: 'notification_type', type: Types::STRING, enumType: NotificationType::class)]
    private NotificationType $notificationType;

    #[ORM\Column(name: 'sender_type', type: Types::STRING, enumType: SenderType::class)]
    private SenderType $senderType;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sender_id', nullable: true)]
    private ?User $sender;

    /**
     * Renseigné uniquement si senderType = PLATFORM_ADMIN (Phase 15) - référence opaque
     * (UUID nu, jamais une relation Doctrine vers App\PlatformAdmin\Entity\
     * PlatformAdministrator) : Notification et PlatformAdmin sont deux modules distincts qui
     * ne partagent jamais directement leurs entités internes (même patron que
     * sourceDiagnosticId ci-dessus, et que App\Shared\Audit\Entity\AuditLogEntry::actorId).
     * `sender` (ManyToOne User) reste réservé exclusivement à senderType = ORGANIZATION_OWNER.
     */
    #[ORM\Column(name: 'platform_admin_sender_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $platformAdminSenderId = null;

    #[ORM\Column(name: 'target_type', type: Types::STRING, enumType: TargetType::class)]
    private TargetType $targetType;

    #[ORM\Column(type: Types::STRING, length: 1000)]
    private string $message;

    #[ORM\Column(type: Types::STRING, enumType: Channel::class)]
    private Channel $channel;

    #[ORM\Column(type: Types::STRING, enumType: NotificationStatus::class)]
    private NotificationStatus $status;

    #[ORM\Column(name: 'source_diagnostic_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $sourceDiagnosticId = null;

    #[ORM\Column(name: 'scheduled_for', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $scheduledFor;

    #[ORM\Column(name: 'sent_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(name: 'read_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    public function __construct(
        ?Organization $organization,
        User $recipientUser,
        NotificationType $notificationType,
        SenderType $senderType,
        ?User $sender,
        TargetType $targetType,
        string $message,
        Channel $channel,
        ?Uuid $platformAdminSenderId = null,
    ) {
        $this->id = Uuid::v7();
        $this->organization = $organization;
        $this->recipientUser = $recipientUser;
        $this->notificationType = $notificationType;
        $this->senderType = $senderType;
        $this->sender = $sender;
        $this->platformAdminSenderId = $platformAdminSenderId;
        $this->targetType = $targetType;
        $this->message = $message;
        $this->channel = $channel;
        // Synchrone (comme AI Gateway, Compliance Engine) : émise et déjà "sent" à la
        // création, jamais de file d'attente pour ce cas (pas de dépendance externe à
        // latence non maîtrisée, ADR-006) - voir App\Notification\Service\
        // SendTeamNotificationService.
        $this->status = NotificationStatus::SENT;
        $this->scheduledFor = new \DateTimeImmutable();
        $this->sentAt = $this->scheduledFor;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrganizationId(): ?Uuid
    {
        return $this->organization?->getId();
    }

    public function getRecipientUser(): User
    {
        return $this->recipientUser;
    }

    public function getNotificationType(): NotificationType
    {
        return $this->notificationType;
    }

    public function getSenderType(): SenderType
    {
        return $this->senderType;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function getPlatformAdminSenderId(): ?Uuid
    {
        return $this->platformAdminSenderId;
    }

    public function getTargetType(): TargetType
    {
        return $this->targetType;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getChannel(): Channel
    {
        return $this->channel;
    }

    public function getStatus(): NotificationStatus
    {
        return $this->status;
    }

    public function getSourceDiagnosticId(): ?Uuid
    {
        return $this->sourceDiagnosticId;
    }

    public function getScheduledFor(): \DateTimeImmutable
    {
        return $this->scheduledFor;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function isRead(): bool
    {
        return null !== $this->readAt;
    }

    public function markRead(): void
    {
        if (null === $this->readAt) {
            $this->readAt = new \DateTimeImmutable();
        }
    }
}
