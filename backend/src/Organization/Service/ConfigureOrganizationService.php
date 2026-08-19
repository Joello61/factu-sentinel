<?php

declare(strict_types=1);

namespace App\Organization\Service;

use App\Compliance\Entity\EligibilityDiagnostic;
use App\Compliance\Service\CompanySizeCategoryResolver;
use App\Compliance\Service\EligibilityDiagnosticCalculator;
use App\Organization\Entity\FiscalContext;
use App\Organization\Entity\Organization;
use App\Organization\Enum\VatStatus;
use App\Organization\Http\MergedFiscalContextInput;
use App\Organization\Repository\FiscalContextRepository;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use Doctrine\ORM\EntityManagerInterface;
use App\Identity\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Orchestration transactionnelle de PATCH /organizations/current (docs plan Phase 3,
 * points 2 et 11 de la revue) : seule responsable de la transaction, de l'ordre de
 * persistance et des écritures d'audit. App\Compliance\Service\EligibilityDiagnosticCalculator
 * reste un calcul pur, jamais appelé en dehors de cette orchestration.
 *
 * Deux invariants de "configuration" indépendants (docs plan Phase 3, point 3 de la revue) :
 * - Organization configurée  <=> legal_name IS NOT NULL (Organization::isConfigured(), déjà
 *   défini en Phase 2, non redéfini ici).
 * - FiscalContext configuré  <=> vat_status + les trois valeurs numériques + la catégorie
 *   dérivée sont toutes non nulles — jamais de ligne partiellement configurée en base.
 * Une requête peut renseigner l'un, l'autre, ou les deux à la fois, indépendamment. Le
 * diagnostic est recalculé chaque fois que fiscal_context est présent dans la requête et
 * complet après fusion avec l'existant, quel que soit le sous-champ effectivement modifié.
 */
final class ConfigureOrganizationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FiscalContextRepository $fiscalContextRepository,
        private readonly CompanySizeCategoryResolver $companySizeCategoryResolver,
        private readonly EligibilityDiagnosticCalculator $eligibilityDiagnosticCalculator,
        private readonly AuditLogger $auditLogger,
        private readonly ValidatorInterface $validator,
        private readonly Security $security,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function configure(Organization $organization, array $payload): ConfigureOrganizationResult
    {
        return $this->entityManager->wrapInTransaction(function () use ($organization, $payload): ConfigureOrganizationResult {
            $now = new \DateTimeImmutable();
            $currentUser = $this->security->getUser();
            \assert($currentUser instanceof User);
            $actorId = $currentUser->getId();

            if (\array_key_exists('legal_name', $payload) && \is_string($payload['legal_name'])) {
                $this->updateLegalName($organization, $payload['legal_name'], $actorId);
            }

            $diagnostic = null;
            $fiscalContext = $this->fiscalContextRepository->findCurrent($organization->getId());

            if (\array_key_exists('fiscal_context', $payload) && \is_array($payload['fiscal_context'])) {
                [$fiscalContext, $calendarRuleVersion] = $this->updateFiscalContext(
                    $organization,
                    $fiscalContext,
                    $payload['fiscal_context'],
                    $now,
                    $actorId,
                );

                $diagnostic = $this->computeAndAuditDiagnostic($organization, $fiscalContext, $calendarRuleVersion, $now, $actorId);
            }

            return new ConfigureOrganizationResult($organization, $fiscalContext, $diagnostic);
        });
    }

    private function updateLegalName(Organization $organization, string $legalName, ?Uuid $actorId): void
    {
        $previousLegalName = $organization->getLegalName();
        $organization->setLegalName($legalName);

        $this->auditLogger->record(
            $organization->getId(),
            ActorType::USER,
            $actorId,
            EventType::ORGANIZATION_UPDATED,
            'Organization',
            $organization->getId()->toRfc4122(),
            ['legal_name' => $previousLegalName],
            ['legal_name' => $organization->getLegalName()],
        );
    }

    /**
     * @param array<string, mixed> $requested
     *
     * @return array{0: FiscalContext, 1: \App\Compliance\Rules\Entity\RuleVersion}
     */
    private function updateFiscalContext(
        Organization $organization,
        ?FiscalContext $current,
        array $requested,
        \DateTimeImmutable $now,
        ?Uuid $actorId,
    ): array {
        $mergedInput = new MergedFiscalContextInput(
            \array_key_exists('vat_status', $requested) ? $requested['vat_status'] : $current?->getVatStatus()->value,
            \array_key_exists('employees_count', $requested) ? $requested['employees_count'] : $current?->getEmployeesCount(),
            \array_key_exists('annual_turnover', $requested) ? $this->stringifyAmount($requested['annual_turnover']) : $current?->getAnnualTurnover(),
            \array_key_exists('annual_balance_sheet_total', $requested) ? $this->stringifyAmount($requested['annual_balance_sheet_total']) : $current?->getAnnualBalanceSheetTotal(),
        );

        $violations = $this->validator->validate($mergedInput);
        if (\count($violations) > 0) {
            throw new ValidationFailedException($mergedInput, $this->toApiFieldNames($violations));
        }

        \assert(\is_string($mergedInput->vatStatus));
        \assert(\is_int($mergedInput->employeesCount));
        \assert(\is_string($mergedInput->annualTurnover));
        \assert(\is_string($mergedInput->annualBalanceSheetTotal));

        $vatStatus = VatStatus::from($mergedInput->vatStatus);

        $resolution = $this->companySizeCategoryResolver->resolve(
            $mergedInput->employeesCount,
            $mergedInput->annualTurnover,
            $mergedInput->annualBalanceSheetTotal,
            $now,
        );

        $previousState = null === $current ? null : [
            'vat_status' => $current->getVatStatus()->value,
            'employees_count' => $current->getEmployeesCount(),
            'annual_turnover' => $current->getAnnualTurnover(),
            'annual_balance_sheet_total' => $current->getAnnualBalanceSheetTotal(),
            'company_size_category' => $current->getCompanySizeCategory()->value,
        ];

        if (null !== $current) {
            $current->close($now);
            // Flush intermédiaire nécessaire : Doctrine UnitOfWork exécute toujours les
            // INSERT avant les UPDATE au sein d'un même flush (vendor/doctrine/orm/src/UnitOfWork.php,
            // UnitOfWork::commit()), quel que soit l'ordre d'appel applicatif. Sans ce flush,
            // l'INSERT du nouveau FiscalContext (effective_until NULL) s'exécuterait avant
            // l'UPDATE qui ferme l'ancien (encore effective_until NULL à cet instant), violant
            // l'index partiel uniq_fiscal_contexts_current_per_organization. Reste dans la
            // même transaction ouverte par wrapInTransaction (pas de commit ici) : l'atomicité
            // globale de PATCH /organizations/current n'est pas affectée.
            $this->entityManager->flush();
        }

        $newFiscalContext = new FiscalContext(
            $organization,
            $vatStatus,
            $mergedInput->employeesCount,
            $mergedInput->annualTurnover,
            $mergedInput->annualBalanceSheetTotal,
            $resolution->category,
            new \DateTimeImmutable($now->format('Y-m-d')),
        );

        $this->entityManager->persist($newFiscalContext);

        $this->auditLogger->record(
            $organization->getId(),
            ActorType::USER,
            $actorId,
            EventType::ORGANIZATION_UPDATED,
            'FiscalContext',
            $newFiscalContext->getId()->toRfc4122(),
            $previousState,
            [
                'vat_status' => $vatStatus->value,
                'employees_count' => $mergedInput->employeesCount,
                'annual_turnover' => $mergedInput->annualTurnover,
                'annual_balance_sheet_total' => $mergedInput->annualBalanceSheetTotal,
                'company_size_category' => $resolution->category->value,
            ],
        );

        return [$newFiscalContext, $resolution->ruleVersion];
    }

    private function computeAndAuditDiagnostic(
        Organization $organization,
        FiscalContext $fiscalContext,
        \App\Compliance\Rules\Entity\RuleVersion $calendarRuleVersion,
        \DateTimeImmutable $now,
        ?Uuid $actorId,
    ): EligibilityDiagnostic {
        $diagnostic = $this->eligibilityDiagnosticCalculator->calculate(
            $organization,
            $fiscalContext,
            $calendarRuleVersion,
            $now,
        );

        $this->entityManager->persist($diagnostic);

        $this->auditLogger->record(
            $organization->getId(),
            ActorType::SYSTEM,
            $actorId,
            EventType::ELIGIBILITY_DIAGNOSTIC_COMPUTED,
            'EligibilityDiagnostic',
            $diagnostic->getId()->toRfc4122(),
            null,
            null,
        );

        return $diagnostic;
    }

    private function stringifyAmount(mixed $value): mixed
    {
        return \is_int($value) || \is_float($value) ? (string) $value : $value;
    }

    /**
     * ApiExceptionListener expose directement getPropertyPath() dans error.details[].field
     * (docs/08-api-specification.md, section 15) : ce champ doit correspondre au nom JSON
     * attendu par le client (snake_case, ex. "annual_turnover"), pas au nom de la propriété
     * PHP interne de MergedFiscalContextInput (camelCase) — remappage local à cet endpoint,
     * plutôt que de complexifier le listener générique pour un seul cas.
     */
    private function toApiFieldNames(ConstraintViolationList $violations): ConstraintViolationList
    {
        $fieldNames = [
            'vatStatus' => 'vat_status',
            'employeesCount' => 'employees_count',
            'annualTurnover' => 'annual_turnover',
            'annualBalanceSheetTotal' => 'annual_balance_sheet_total',
        ];

        $remapped = new ConstraintViolationList();
        foreach ($violations as $violation) {
            $propertyPath = $fieldNames[$violation->getPropertyPath()] ?? $violation->getPropertyPath();
            $remapped->add(new ConstraintViolation(
                $violation->getMessage(),
                $violation->getMessageTemplate(),
                $violation->getParameters(),
                $violation->getRoot(),
                $propertyPath,
                $violation->getInvalidValue(),
                $violation->getPlural(),
                $violation->getCode(),
                $violation->getConstraint(),
                $violation->getCause(),
            ));
        }

        return $remapped;
    }
}
