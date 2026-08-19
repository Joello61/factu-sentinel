<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint d'exploitation, pas une ressource métier : volontairement sous /api/health
 * plutôt que /api/v1/health, /api/v1 étant réservé au contrat métier versionné
 * (docs/08-api-specification.md, section 5).
 *
 * Vérifie explicitement la connexion à PostgreSQL (docs/12-roadmap.md, Phase 1,
 * Exit Criteria : un appel de bout en bout Next.js -> Symfony -> PostgreSQL fonctionne).
 * Ne suit pas encore le contrat d'erreur formel complet de docs/08-api-specification.md
 * (section 14 : request_id notamment) : ce mécanisme transverse sera mis en place avec le
 * premier endpoint métier réel, pas anticipé ici pour une simple sonde technique.
 */
final class HealthController
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    #[Route('/api/health', name: 'health_check', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (DbalException) {
            return new JsonResponse(
                [
                    'error' => [
                        'code' => 'DATABASE_UNAVAILABLE',
                        'message' => 'La base de données est inaccessible.',
                    ],
                ],
                JsonResponse::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return new JsonResponse([
            'data' => [
                'status' => 'ok',
                'database' => 'ok',
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }
}
