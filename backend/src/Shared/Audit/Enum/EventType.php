<?php

declare(strict_types=1);

namespace App\Shared\Audit\Enum;

/**
 * docs/07-data-model.md, section 20 cite plusieurs event_type possibles (login,
 * organization_updated, invoice_created, ...) mais seuls les événements réellement
 * produits par les phases déjà implémentées sont listés ici (docs/06-technical-architecture.md,
 * section 22, et backend/CLAUDE.md section 15 : implémenter le minimum nécessaire) : les
 * événements de Phase 2 (login, réinitialisation de mot de passe) ne sont volontairement pas
 * instrumentés rétroactivement dans cette tâche, ce gap préexistant relevant du
 * durcissement sécurité de la Phase 10 (voir plan Phase 3).
 */
enum EventType: string
{
    case ORGANIZATION_UPDATED = 'ORGANIZATION_UPDATED';
    case ELIGIBILITY_DIAGNOSTIC_COMPUTED = 'ELIGIBILITY_DIAGNOSTIC_COMPUTED';
    case CUSTOMER_CREATED = 'CUSTOMER_CREATED';
    case CUSTOMER_UPDATED = 'CUSTOMER_UPDATED';
    case CUSTOMER_DELETED = 'CUSTOMER_DELETED';
    case INVOICE_CREATED = 'INVOICE_CREATED';
    case INVOICE_UPDATED = 'INVOICE_UPDATED';

    /**
     * Ajoutés en Phase 5 (SEC-AUDIT-001, docs/10-security-privacy.md section 63) :
     * COMPLIANCE_ANALYSIS_FAILED est déclaré pour fidélité au modèle mais n'est produit
     * par aucun code chemin en Phase 5 (traitement toujours synchrone, une erreur
     * technique fait échouer toute la transaction avant tout audit -- voir
     * App\Compliance\Engine\Service\RunComplianceAnalysisService) : à ne pas utiliser
     * avant qu'un vrai chemin (Phase 7, asynchrone) l'émette.
     */
    case COMPLIANCE_ANALYSIS_COMPLETED = 'COMPLIANCE_ANALYSIS_COMPLETED';
    case COMPLIANCE_ANALYSIS_FAILED = 'COMPLIANCE_ANALYSIS_FAILED';

    /** Ajoutés en Phase 7 (Document Processing, docs/12-roadmap.md). */
    case DOCUMENT_UPLOADED = 'DOCUMENT_UPLOADED';
    case DOCUMENT_DELETED = 'DOCUMENT_DELETED';

    /**
     * Ajoutés en Phase 8 (AI Assistant, docs/12-roadmap.md). newState ne porte jamais le
     * prompt ni le texte généré (docs/10-security-privacy.md, section 35 : "jamais loggés...
     * prompts ou réponses IA contenant des données personnelles") - uniquement un indicateur
     * de succès et un identifiant/une longueur, enregistré que l'appel Mistral réussisse ou
     * échoue (App\AI\Service\ExplainComplianceFindingService, App\AI\Service\
     * AnswerAssistantQuestionService).
     */
    case COMPLIANCE_FINDING_EXPLAINED = 'COMPLIANCE_FINDING_EXPLAINED';
    case ASSISTANT_QUESTION_ASKED = 'ASSISTANT_QUESTION_ASKED';

    /**
     * Ajoutés en Phase 13 (Paramètres & Profil utilisateur, docs/12-roadmap.md).
     * USER_UPDATED ne porte jamais de hash de mot de passe ni la valeur de current_password/
     * new_password dans previousState/newState (docs/10-security-privacy.md, section 35) -
     * uniquement l'email (si modifié) et un indicateur password_changed.
     */
    case USER_UPDATED = 'USER_UPDATED';
    case USER_DELETED = 'USER_DELETED';

    /**
     * Ajoutés en Phase 14 (Rôles d'organisation & Notifications internes,
     * docs/12-roadmap.md). MEMBER_INVITED/MEMBER_INVITATION_ACCEPTED ne portent jamais le
     * jeton d'invitation, en clair ou haché, dans previousState/newState (même discipline
     * que USER_UPDATED ci-dessus pour les mots de passe) - uniquement Invitation.id.
     * TEAM_NOTIFICATION_SENT ne porte jamais le contenu du message, uniquement le nombre de
     * destinataires.
     */
    case MEMBER_INVITED = 'MEMBER_INVITED';
    case MEMBER_INVITATION_ACCEPTED = 'MEMBER_INVITATION_ACCEPTED';
    case MEMBER_INVITATION_REVOKED = 'MEMBER_INVITATION_REVOKED';
    case MEMBER_ROLE_CHANGED = 'MEMBER_ROLE_CHANGED';
    case MEMBER_REMOVED = 'MEMBER_REMOVED';
    case TEAM_NOTIFICATION_SENT = 'TEAM_NOTIFICATION_SENT';

    /**
     * Ajoutés en Phase 15 (Administration plateforme, docs/12-roadmap.md ; ADR-009,
     * docs/10-security-privacy.md section 17 bis : "chaque lecture ou écriture cross-tenant
     * est journalisée, sans exception"). PLATFORM_AUDIT_TRAIL_VIEWED couvre la consultation
     * elle-même, pas seulement les écritures - explicitement exigé par cette section.
     * PLATFORM_NOTIFICATION_SENT ne porte jamais le contenu du message ni les critères de
     * segmentation bruts au-delà d'un décompte de destinataires (même discipline que
     * TEAM_NOTIFICATION_SENT ci-dessus).
     */
    case PLATFORM_ADMIN_LOGIN = 'PLATFORM_ADMIN_LOGIN';
    case PLATFORM_ORGANIZATION_SUSPENDED = 'PLATFORM_ORGANIZATION_SUSPENDED';
    case PLATFORM_ORGANIZATION_REACTIVATED = 'PLATFORM_ORGANIZATION_REACTIVATED';
    case PLATFORM_AUDIT_TRAIL_VIEWED = 'PLATFORM_AUDIT_TRAIL_VIEWED';
    case PLATFORM_NOTIFICATION_SENT = 'PLATFORM_NOTIFICATION_SENT';
    case PLATFORM_ORGANIZATIONS_VIEWED = 'PLATFORM_ORGANIZATIONS_VIEWED';

    /**
     * Ajoutés en Phase 16 (Stats & Analytics métier, docs/12-roadmap.md). PLATFORM_HEALTH_VIEWED
     * ferme un écart de la Phase 15 (US-PLATFORMADMIN-005 relisait les indicateurs de santé
     * applicative cross-tenant sans jamais l'auditer, en violation de docs/10-security-privacy.md
     * section 17 bis) - événement dédié, jamais une réutilisation de PLATFORM_AUDIT_TRAIL_VIEWED
     * (qui désigne exclusivement la consultation de l'audit trail lui-même). PLATFORM_ANALYTICS_VIEWED
     * couvre les deux endpoints Analytics (résumé et tendances, US-ANALYTICS-001/002) - un seul
     * event type par famille de ressource, même granularité que PLATFORM_ORGANIZATIONS_VIEWED
     * ci-dessus (qui couvre déjà liste et détail).
     */
    case PLATFORM_HEALTH_VIEWED = 'PLATFORM_HEALTH_VIEWED';
    case PLATFORM_ANALYTICS_VIEWED = 'PLATFORM_ANALYTICS_VIEWED';
}
