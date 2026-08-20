<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Identity\Entity\User;
use App\Shared\Exception\EmailVerificationRequiredException;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Vérification email obligatoire avant toute fonctionnalité sensible (upload, analyses
 * persistantes, usage de l'IA - docs/10-security-privacy.md, section 12). Introduite en
 * Phase 8 dupliquée dans App\AI\Service\ExplainComplianceFindingService et
 * App\AI\Service\AnswerAssistantQuestionService ; centralisée ici en Phase 10 en l'étendant
 * à App\Document\Service\UploadDocumentService et
 * App\Compliance\Engine\Service\RunComplianceAnalysisService (dette documentée
 * docs/12-roadmap.md, Phase 8).
 */
final class EmailVerificationGuard
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function assertVerified(): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isEmailVerified()) {
            throw new EmailVerificationRequiredException();
        }
    }
}
