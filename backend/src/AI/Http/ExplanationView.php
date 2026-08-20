<?php

declare(strict_types=1);

namespace App\AI\Http;

/**
 * docs/08-api-specification.md, section 35. "source" est la chaîne fixe déjà spécifiée dans
 * l'exemple de réponse du contrat - jamais une valeur inventée ici (ex. un code interne) :
 * elle n'est pas censée nommer le fournisseur IA, seulement rappeler que le texte est une
 * reformulation assistée d'un résultat déjà déterminé.
 */
final class ExplanationView
{
    private const string SOURCE = 'Généré par assistance IA à partir du résultat déterministe existant';

    /** @return array<string, mixed> */
    public static function create(string $findingId, string $explanation): array
    {
        return [
            'finding_id' => $findingId,
            'explanation' => $explanation,
            'source' => self::SOURCE,
        ];
    }
}
