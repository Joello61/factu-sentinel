<?php

declare(strict_types=1);

namespace App\AI\Service;

use App\AI\Exception\AiProviderUnavailableException;

/**
 * Abstraction du fournisseur IA (docs/06-technical-architecture.md, section 15, 17 ; ADR-005
 * et ADR-007). Volontairement agnostique de ComplianceFinding ou de toute autre entité
 * métier : la construction du prompt (contexte minimisé, garde-fous anti-injection)
 * appartient à App\AI\Service\AIGateway, jamais à cette interface ni à ses implémentations -
 * changer de fournisseur ne doit donc jamais toucher au reste du module AI.
 *
 * Aucune méthode d'appel d'outil/fonction n'est exposée ici : le fournisseur ne reçoit
 * jamais de définition d'outil (docs/10-security-privacy.md, section 31 : "aucun outil
 * d'action"), il ne peut produire que du texte.
 */
interface AIProviderInterface
{
    /**
     * @throws AiProviderUnavailableException si le fournisseur ne répond pas correctement
     */
    public function complete(string $systemPrompt, string $userPrompt, float $timeoutSeconds): string;
}
