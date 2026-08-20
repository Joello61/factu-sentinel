<?php

declare(strict_types=1);

namespace App\AI\Exception;

/**
 * Le fournisseur IA (Mistral) n'a pas répondu correctement - timeout, erreur réseau, statut
 * HTTP non 200 (docs/06-technical-architecture.md, section 14-15 : un échec du fournisseur
 * ne doit jamais bloquer l'affichage du résultat déterministe déjà produit). Les deux
 * services applicatifs (App\AI\Service\ExplainComplianceFindingService, App\AI\Service\
 * AnswerAssistantQuestionService) laissent cette exception se propager jusqu'au contrôleur,
 * qui la traduit en 503 (docs/08-api-specification.md, section 35).
 */
final class AiProviderUnavailableException extends \RuntimeException
{
}
