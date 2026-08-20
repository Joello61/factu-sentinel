<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Déclare les chemins d'authentification comme de vraies routes Symfony. Sans route
 * enregistrée pour un check_path, le RouterListener (kernel.request) lève "No route
 * found" avant que json_login/refresh-jwt n'aient l'occasion d'intercepter la requête sur
 * ce firewall stateless - constaté empiriquement en écrivant les tests d'auth (voir plan
 * Phase 2). Ce contrôleur ne doit jamais être réellement exécuté : les authenticators
 * interceptent la requête avant que le contrôleur ne soit invoqué.
 */
final class UnreachableAuthController
{
    #[Route('/api/v1/auth/login', name: 'auth_login_check', methods: ['POST'])]
    #[Route('/api/v1/auth/refresh', name: 'auth_refresh_check', methods: ['POST'])]
    public function __invoke(): Response
    {
        throw new \LogicException('This controller should never be reached: the security firewall should have intercepted this request.');
    }
}
