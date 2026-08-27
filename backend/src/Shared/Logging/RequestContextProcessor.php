<?php

declare(strict_types=1);

namespace App\Shared\Logging;

use App\Identity\Entity\User;
use App\Shared\Http\RequestIdListener;
use App\Shared\Security\CurrentOrganizationResolver;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * Corrèle chaque ligne de log à sa requête (Phase 18, étape 1) : ajoute request_id
 * (RequestIdListener, déjà propagé au client), organization_id et user_id quand la requête
 * est authentifiée. Uniquement des identifiants UUID techniques - jamais d'email, de SIREN,
 * de montant ou de contenu de document/prompt IA dans le contexte de log
 * (docs/10-security-privacy.md section 35).
 */
#[AsMonologProcessor]
final class RequestContextProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return $record;
        }

        $requestId = $request->attributes->get(RequestIdListener::ATTRIBUTE);
        if (is_string($requestId)) {
            $record->extra['request_id'] = $requestId;
        }

        $organizationId = $request->attributes->get(CurrentOrganizationResolver::ATTRIBUTE);
        if ($organizationId instanceof Uuid) {
            $record->extra['organization_id'] = $organizationId->toRfc4122();
        }

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $record->extra['user_id'] = $user->getId()->toRfc4122();
        }

        return $record;
    }
}
