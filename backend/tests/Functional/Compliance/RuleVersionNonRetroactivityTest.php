<?php

declare(strict_types=1);

namespace App\Tests\Functional\Compliance;

use App\Tests\Support\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Uuid;

/**
 * E2E-006 (docs/09-test-strategy.md, section 38 ; docs/12-roadmap.md, Phase 9 Exit
 * Criteria) : une analyse historique consultée reste strictement inchangée lorsqu'une
 * nouvelle RuleVersion est publiée en arrière-plan, tandis qu'une nouvelle analyse utilise
 * la nouvelle version.
 *
 * Publication faite par le mécanisme réel du projet, jamais un mock qui contournerait la
 * persistance : aucune API de publication de règle n'existe au MVP
 * (docs/08-api-specification.md, section 32 - "Non exposé : GET /regulatory-rules") ; le
 * seul précédent réel est backend/migrations/Version20260820100002.php (Phase 7, v2 de
 * format-facture-electronique), qui ferme l'ancienne version par un UPDATE SQL direct sur
 * `effective_until` (RuleVersion n'expose volontairement aucun setter, ADR-003) puis insère
 * la nouvelle. Ce test reproduit exactement ce schéma via l'EntityManager/Connection, pour
 * mention-siren-client plutôt que de toucher au référentiel seedé de format-facture-electronique.
 */
final class RuleVersionNonRetroactivityTest extends ApiTestCase
{
    /**
     * mention-siren-client est un référentiel global, jamais tenant-scoped
     * (App\Compliance\Rules\Entity\RegulatoryRule, docs/06-technical-architecture.md section
     * 9) : contrairement aux autres tests de ce projet, dont les effets restent isolés par
     * organisation, la publication d'une nouvelle RuleVersion ici modifie un état partagé par
     * toute la suite. Sans ce nettoyage, tout test lancé après celui-ci dans le même
     * processus (ex. App\Tests\Functional\Compliance\RunComplianceAnalysisControllerTest::
     * testMissingSirenProducesNonConformeWithCorrectionAction, qui vérifie en dur
     * rule.version === 1) échouerait à tort - constaté à l'implémentation de ce test.
     */
    private ?string $publishedVersionId = null;
    private ?string $closedVersionId = null;

    /**
     * Appelé depuis un `finally` du corps du test : neutralise la v2 pour ne pas polluer les
     * autres tests de la suite, mention-siren-client étant un référentiel global partagé par
     * tout le processus (voir docblock de $publishedVersionId ci-dessus). Jamais un DELETE :
     * la ComplianceFinding de l'analyse #2 référence réellement rule_version_id = v2 (jamais
     * supprimée physiquement, backend/CLAUDE.md section 7) - une suppression violerait la
     * contrainte de clé étrangère fk_compliance_findings_rule_version_id (constaté à
     * l'implémentation). La v2 reste donc présente en base, mais rendue à tout jamais
     * inactive en fixant sa fenêtre de validité à une durée nulle (effective_until =
     * effective_from) : RuleVersionRepository::findActive() ne peut alors plus jamais la
     * sélectionner (effectiveUntil > :at devient toujours faux), sans créer le moindre
     * chevauchement futur avec la v1 réactivée ci-dessous.
     */
    private function cleanupPublishedRuleVersion(): void
    {
        if (null === $this->publishedVersionId) {
            return;
        }

        $connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $connection->executeStatement(
            'UPDATE rule_versions SET effective_until = effective_from WHERE id = ?',
            [$this->publishedVersionId],
        );
        $connection->executeStatement('UPDATE rule_versions SET effective_until = NULL WHERE id = ?', [$this->closedVersionId]);
    }

    private function configureFiscalContext(KernelBrowser $client): void
    {
        $client->jsonRequest('PATCH', '/api/v1/organizations/current', [
            'fiscal_context' => [
                'vat_status' => 'ASSUJETTI_REDEVABLE',
                'employees_count' => 5,
                'annual_turnover' => '200000',
                'annual_balance_sheet_total' => '150000',
            ],
        ]);
        self::assertResponseStatusCodeSame(200);
    }

    /**
     * Ferme la RuleVersion v1 de mention-siren-client (seedée en Phase 5,
     * backend/migrations/Version20260819200000.php) et publie une v2 avec des messages
     * volontairement différents - si un finding déjà persisté affichait (à tort) un texte
     * recalculé dynamiquement depuis la RuleVersion courante plutôt que sa copie figée à la
     * création (docs/07-data-model.md, section 18), ce test le détecterait immédiatement, le
     * texte de la v2 apparaissant à la place de celui de la v1.
     *
     * effective_until de la v1 = effective_from de la v2 (jamais "la veille") :
     * App\Compliance\Rules\Repository\RuleVersionRepository::findActive() teste une borne
     * inclusive à gauche et exclusive à droite - une valeur "veille" créerait un jour sans
     * version active (docs/07-data-model.md, section 37, même raisonnement documenté dans
     * Version20260820100002.php).
     *
     * Requête SQL directe uniquement (jamais l'EntityManager ORM pour cette étape) :
     * RuleVersion n'expose aucun setter, y compris pour effective_until (ADR-003) - c'est la
     * seule mutation sanctionnée sur cette entité, jamais faite via son API PHP.
     */
    private function publishNewSirenRuleVersion(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $previousVersionId = $connection->fetchOne(
            "SELECT id FROM rule_versions WHERE rule_id = 'mention-siren-client' AND effective_until IS NULL",
        );
        self::assertIsString($previousVersionId, 'La RuleVersion v1 de mention-siren-client (seedée en Phase 5) doit exister et être active à ce stade.');
        $this->closedVersionId = $previousVersionId;

        $connection->executeStatement(
            'UPDATE rule_versions SET effective_until = ? WHERE id = ?',
            [$today, $previousVersionId],
        );

        $conditions = json_encode([
            'applicability' => ['customer_types' => ['PROFESSIONNEL_FRANCAIS'], 'requires_non_exempt' => true],
            'outcomes' => [
                'CONFORME' => ['message' => 'v2 (test E2E-006) - SIREN présent, message distinct de la v1.'],
                'NON_CONFORME' => [
                    'message' => 'v2 (test E2E-006) - SIREN absent, message distinct de la v1.',
                    'correction_action' => 'v2 (test E2E-006) - corrigez le SIREN, action distincte de la v1.',
                ],
                'NON_APPLICABLE' => ['message' => "Cette vérification concerne les mentions obligatoires de la facturation électronique B2B domestique : elle ne s'applique pas à ce client ou à cette opération (client particulier, étranger, ou opération exonérée de TVA)."],
            ],
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        $newVersionId = Uuid::v7()->toRfc4122();
        $connection->executeStatement(
            'INSERT INTO rule_versions (id, version_number, effective_from, effective_until, conditions, severity, source_reference, confidence_level, explanation_template, created_at, rule_id) VALUES (?, 2, ?, NULL, ?, ?, ?, ?, ?, NOW(), ?)',
            [
                $newVersionId,
                $today,
                $conditions,
                'NON_CONFORME',
                'docs/02-regulatory-study.md, section 10 (E2E-006, version de test)',
                'ELEVE',
                'v2 (test E2E-006) - explication distincte de la v1.',
                'mention-siren-client',
            ],
        );
        $this->publishedVersionId = $newVersionId;
    }

    /**
     * @param array<int, array<string, mixed>> $findings
     *
     * @return array<string, mixed>
     */
    private function sirenFinding(array $findings): array
    {
        $matches = array_values(array_filter(
            $findings,
            static fn (array $finding): bool => 'mention-siren-client' === $finding['rule']['id'],
        ));
        self::assertCount(1, $matches, 'Le finding SIREN doit être présent exactement une fois dans cette analyse.');

        return $matches[0];
    }

    public function testHistoricalAnalysisIsUnaffectedByANewlyPublishedRuleVersion(): void
    {
        try {
            $this->runScenario();
        } finally {
            $this->cleanupPublishedRuleVersion();
        }
    }

    private function runScenario(): void
    {
        $client = $this->createAuthenticatedClient('e2e-006-non-retro@example.test');
        $this->markEmailVerified('e2e-006-non-retro@example.test');
        $this->configureFiscalContext($client);

        $client->jsonRequest('POST', '/api/v1/customers', [
            'customer_type' => 'PROFESSIONNEL_FRANCAIS',
            'name' => 'Client E2E-006 SARL',
            'siren' => null,
            'country' => 'FR',
        ]);
        $customerId = $this->jsonBody($client)['data']['id'];

        $client->jsonRequest('POST', '/api/v1/invoices', [
            'customer_id' => $customerId,
            'operation_type' => 'PRESTATION_SERVICE',
            'issue_date' => '2026-08-15',
            'currency' => 'EUR',
            'lines' => [['description' => 'Prestation', 'quantity' => '1', 'unit_price_ht' => '100.00', 'vat_rate' => '0.20']],
        ], ['HTTP_IDEMPOTENCY_KEY' => 'e2e-006-invoice-'.bin2hex(random_bytes(8))]);
        $invoiceId = $this->jsonBody($client)['data']['id'];

        // Analyse #1, sous la RuleVersion v1 (seedée en Phase 5, toujours active à ce stade).
        $client->jsonRequest('POST', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId), [], ['HTTP_IDEMPOTENCY_KEY' => 'e2e-006-analysis-1']);
        self::assertResponseStatusCodeSame(200);
        $firstAnalysis = $this->jsonBody($client)['data'];
        $firstAnalysisId = $firstAnalysis['id'];

        $firstSirenFinding = $this->sirenFinding($firstAnalysis['findings']);
        self::assertSame('NON_CONFORME', $firstSirenFinding['result']);
        self::assertSame(1, $firstSirenFinding['rule']['version']);
        self::assertSame(
            "Le numéro SIREN de votre client professionnel français est absent de cette facture. Cette mention est obligatoire pour les factures B2B domestiques depuis la réforme de la facturation électronique.",
            $firstSirenFinding['message'],
        );
        self::assertSame(
            "Renseignez le numéro SIREN de votre client dans sa fiche client, puis relancez l'analyse.",
            $firstSirenFinding['correction_action'],
        );
        self::assertNull($firstSirenFinding['rule']['effective_until'], 'La v1 est encore active à ce stade.');

        // Publication de la RuleVersion v2 en arrière-plan (jamais via l'API utilisateur -
        // aucune n'existe au MVP pour cela).
        $this->publishNewSirenRuleVersion();

        // Analyse #2, sur la même facture (toujours sans SIREN), désormais sous la v2.
        $client->jsonRequest('POST', sprintf('/api/v1/invoices/%s/compliance-analyses', $invoiceId), [], ['HTTP_IDEMPOTENCY_KEY' => 'e2e-006-analysis-2']);
        self::assertResponseStatusCodeSame(200);
        $secondAnalysis = $this->jsonBody($client)['data'];
        self::assertNotSame(
            $firstAnalysisId,
            $secondAnalysis['id'],
            'Une nouvelle ComplianceAnalysis distincte, jamais un remplacement de la première (US-COMPLIANCE-006).',
        );

        $secondSirenFinding = $this->sirenFinding($secondAnalysis['findings']);
        self::assertSame('NON_CONFORME', $secondSirenFinding['result']);
        self::assertSame(2, $secondSirenFinding['rule']['version'], 'La nouvelle analyse utilise la nouvelle version de la règle.');
        self::assertSame('v2 (test E2E-006) - SIREN absent, message distinct de la v1.', $secondSirenFinding['message']);
        self::assertSame('v2 (test E2E-006) - corrigez le SIREN, action distincte de la v1.', $secondSirenFinding['correction_action']);

        // Coeur d'E2E-006 : l'analyse historique (#1), reconsultée après la publication de la
        // v2, reste strictement identique à ce qui a été observé avant publication.
        $client->jsonRequest('GET', '/api/v1/compliance-analyses/'.$firstAnalysisId);
        self::assertResponseStatusCodeSame(200);
        $refetchedFirstAnalysis = $this->jsonBody($client)['data'];

        self::assertSame($firstAnalysis['global_result'], $refetchedFirstAnalysis['global_result']);
        self::assertSame($firstAnalysis['status'], $refetchedFirstAnalysis['status']);
        self::assertSame($firstAnalysis['triggered_at'], $refetchedFirstAnalysis['triggered_at']);
        self::assertSame($firstAnalysis['completed_at'], $refetchedFirstAnalysis['completed_at']);

        $refetchedSirenFinding = $this->sirenFinding($refetchedFirstAnalysis['findings']);
        self::assertSame($firstSirenFinding['id'], $refetchedSirenFinding['id']);
        self::assertSame($firstSirenFinding['result'], $refetchedSirenFinding['result']);
        self::assertSame(
            $firstSirenFinding['message'],
            $refetchedSirenFinding['message'],
            'Le message reste celui figé à la création du finding (v1), jamais recalculé depuis la RuleVersion courante (v2).',
        );
        self::assertSame($firstSirenFinding['correction_action'], $refetchedSirenFinding['correction_action']);
        self::assertSame($firstSirenFinding['rule']['id'], $refetchedSirenFinding['rule']['id']);
        self::assertSame(
            1,
            $refetchedSirenFinding['rule']['version'],
            "L'analyse historique référence toujours rule_version_id de la v1, ne bascule jamais vers la v2.",
        );
        self::assertSame($firstSirenFinding['rule']['source_reference'], $refetchedSirenFinding['rule']['source_reference']);
        self::assertSame($firstSirenFinding['rule']['confidence_level'], $refetchedSirenFinding['rule']['confidence_level']);
        self::assertSame($firstSirenFinding['rule']['effective_from'], $refetchedSirenFinding['rule']['effective_from']);

        // Seul champ qui change légitimement : effective_until de la v1 référencée reflète
        // désormais sa vraie période de validité (fin = date de publication de la v2) - ce
        // n'est jamais une modification du finding lui-même (ADR-003 : la seule mutation
        // sanctionnée porte sur RuleVersion.effective_until au moment de la publication,
        // jamais sur le contenu figé du finding, vérifié juste au-dessus).
        self::assertNotNull($refetchedSirenFinding['rule']['effective_until'], 'La v1 est désormais close, sa fin de validité doit être renseignée.');

        // GET .../findings doit rester cohérent avec l'analyse embarquée (même invariant que
        // App\Tests\Functional\Compliance\GetComplianceAnalysisControllerTest::testFindingsEndpointMatchesEmbeddedFindings).
        $client->jsonRequest('GET', '/api/v1/compliance-analyses/'.$firstAnalysisId.'/findings');
        self::assertResponseStatusCodeSame(200);
        self::assertSame($refetchedFirstAnalysis['findings'], $this->jsonBody($client)['data']);
    }
}
