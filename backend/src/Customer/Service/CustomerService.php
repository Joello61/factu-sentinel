<?php

declare(strict_types=1);

namespace App\Customer\Service;

use App\Customer\Entity\Customer;
use App\Customer\Enum\CustomerType;
use App\Customer\Http\CreateCustomerRequest;
use App\Customer\Http\UpdateCustomerInput;
use App\Identity\Entity\User;
use App\Organization\Entity\Organization;
use App\Shared\Audit\AuditLogger;
use App\Shared\Audit\Enum\ActorType;
use App\Shared\Audit\Enum\EventType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Orchestration transactionnelle de POST/PATCH/DELETE /customers, même style que
 * App\Organization\Service\ConfigureOrganizationService : seul responsable de la
 * transaction et des écritures d'audit.
 */
final class CustomerService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogger $auditLogger,
        private readonly ValidatorInterface $validator,
        private readonly Security $security,
    ) {
    }

    public function create(Organization $organization, CreateCustomerRequest $request): Customer
    {
        return $this->entityManager->wrapInTransaction(function () use ($organization, $request): Customer {
            $customer = new Customer(
                $organization,
                CustomerType::from($request->customerType),
                $request->name,
                $request->siren,
                $request->vatNumber,
                (string) $request->country,
            );

            $this->entityManager->persist($customer);

            $this->auditLogger->record(
                $organization->getId(),
                ActorType::USER,
                $this->currentActorId(),
                EventType::CUSTOMER_CREATED,
                'Customer',
                $customer->getId()->toRfc4122(),
                null,
                $this->snapshot($customer),
            );

            return $customer;
        });
    }

    /** @param array<string, mixed> $payload */
    public function update(Customer $customer, array $payload): Customer
    {
        return $this->entityManager->wrapInTransaction(function () use ($customer, $payload): Customer {
            $previousState = $this->snapshot($customer);

            $input = new UpdateCustomerInput(
                \array_key_exists('customer_type', $payload) ? $payload['customer_type'] : $customer->getCustomerType()->value,
                \array_key_exists('name', $payload) ? $payload['name'] : $customer->getName(),
                \array_key_exists('siren', $payload) ? $payload['siren'] : $customer->getSiren(),
                \array_key_exists('vat_number', $payload) ? $payload['vat_number'] : $customer->getVatNumber(),
                \array_key_exists('country', $payload) ? $payload['country'] : $customer->getCountry(),
            );

            $violations = $this->validator->validate($input);
            if (\count($violations) > 0) {
                throw new ValidationFailedException($input, $violations);
            }

            \assert(\is_string($input->customerType));
            \assert(\is_string($input->name));
            \assert(\is_string($input->country));

            $customer->setCustomerType(CustomerType::from($input->customerType));
            $customer->setName($input->name);
            $customer->setSiren($input->siren);
            $customer->setVatNumber($input->vatNumber);
            $customer->setCountry($input->country);
            $customer->touch();

            $this->auditLogger->record(
                $customer->getOrganizationId(),
                ActorType::USER,
                $this->currentActorId(),
                EventType::CUSTOMER_UPDATED,
                'Customer',
                $customer->getId()->toRfc4122(),
                $previousState,
                $this->snapshot($customer),
            );

            return $customer;
        });
    }

    public function delete(Customer $customer): void
    {
        $this->entityManager->wrapInTransaction(function () use ($customer): void {
            $customer->markDeleted(new \DateTimeImmutable());

            $this->auditLogger->record(
                $customer->getOrganizationId(),
                ActorType::USER,
                $this->currentActorId(),
                EventType::CUSTOMER_DELETED,
                'Customer',
                $customer->getId()->toRfc4122(),
                null,
                null,
            );
        });
    }

    private function currentActorId(): ?Uuid
    {
        $currentUser = $this->security->getUser();

        return $currentUser instanceof User ? $currentUser->getId() : null;
    }

    /** @return array<string, mixed> */
    private function snapshot(Customer $customer): array
    {
        return [
            'customer_type' => $customer->getCustomerType()->value,
            'name' => $customer->getName(),
            'siren' => $customer->getSiren(),
            'vat_number' => $customer->getVatNumber(),
            'country' => $customer->getCountry(),
        ];
    }
}
