<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AccountOperationResource;
use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Repository\AccountOperationRepository;
use App\Repository\AccountRepository;
use App\Service\ContractorResolver;
use App\Util\BusinessDateRange;
use App\Util\DateParser;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<mixed, ?AccountOperationResource>
 */
final readonly class AccountOperationProcessor implements ProcessorInterface
{
    public function __construct(
        private AccountOperationRepository $accountOperationRepository,
        private AccountRepository $accountRepository,
        private ContractorResolver $contractorResolver,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ?AccountOperationResource {
        if ($operation instanceof Delete) {
            $this->accountOperationRepository->remove($this->loadExisting($uriVariables), flush: true);

            return null;
        }

        if (!$data instanceof AccountOperationResource) {
            throw new \InvalidArgumentException('Expected instance of AccountOperationResource.');
        }

        try {
            $existingOperation = $operation instanceof Patch ? $this->loadExisting($uriVariables) : null;
            $account = $existingOperation?->getAccount() ?? $this->resolveAccount($data->accountId);
            $operationDate = $this->parseOperationDate($data->operationDate);
            $amount = $this->parseAmount($data->amount, $account->getCurrencyCode());

            // Include the resolver's contractor flush and the final operation flush in one transaction.
            /** @var AccountOperation $accountOperation */
            $accountOperation = $this->entityManager->wrapInTransaction(function () use (
                $existingOperation,
                $account,
                $data,
                $operationDate,
                $amount,
            ): AccountOperation {
                $accountOperation = null === $existingOperation
                    ? $this->create($account, $data, $operationDate, $amount)
                    : $this->update($existingOperation, $data, $operationDate, $amount);

                $this->accountOperationRepository->save($accountOperation);

                return $accountOperation;
            });
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return AccountOperationResource::fromEntity($accountOperation);
    }

    private function create(
        Account $account,
        AccountOperationResource $data,
        \DateTimeImmutable $operationDate,
        int $amount,
    ): AccountOperation {
        return new AccountOperation(
            $account,
            $this->contractorResolver->resolve($data->contractorId, $data->contractorName),
            $operationDate,
            $amount,
            $account->getCurrency(),
            description: $data->description,
            confirmed: $data->confirmed,
        );
    }

    private function update(
        AccountOperation $accountOperation,
        AccountOperationResource $data,
        \DateTimeImmutable $operationDate,
        int $amount,
    ): AccountOperation {
        $contractor = $this->contractorResolver->resolveForUpdate(
            $data->contractorId,
            $data->contractorName,
            $accountOperation->getContractor(),
        );

        $accountOperation->setAmount($amount);
        $accountOperation->setContractor($contractor);
        $accountOperation->setOperationDate($operationDate);
        $accountOperation->setDescription($data->description);
        $accountOperation->setConfirmed($data->confirmed);

        return $accountOperation;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function loadExisting(array $uriVariables): AccountOperation
    {
        $accountOperation = $this->accountOperationRepository->findByIdOrNull($uriVariables['id'] ?? null);

        if (!$accountOperation) {
            throw new NotFoundHttpException('Account operation not found.');
        }

        return $accountOperation;
    }

    private function resolveAccount(?int $accountId): Account
    {
        if (null === $accountId) {
            throw new UnprocessableEntityHttpException('Account is required.');
        }

        $account = $this->accountRepository->findByIdOrNull($accountId);
        if (!$account) {
            throw new NotFoundHttpException('Account not found.');
        }

        return $account;
    }

    private function parseAmount(?string $amount, string $currencyCode): int
    {
        if (null === $amount) {
            throw new UnprocessableEntityHttpException('Amount is required.');
        }

        $minorUnits = Money::fromDecimalString($amount, $currencyCode)->getMinorUnits();
        AccountOperation::assertValidAmount($minorUnits);

        return $minorUnits;
    }

    private function parseOperationDate(?string $operationDate): \DateTimeImmutable
    {
        if (null === $operationDate) {
            throw new UnprocessableEntityHttpException('Operation date is required.');
        }

        $date = DateParser::tryParse($operationDate);
        if (null === $date) {
            throw new UnprocessableEntityHttpException('Invalid operation date format.');
        }

        if (!BusinessDateRange::contains($date)) {
            throw new UnprocessableEntityHttpException(BusinessDateRange::ERROR_MESSAGE);
        }

        return $date;
    }
}
