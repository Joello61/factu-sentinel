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
}
