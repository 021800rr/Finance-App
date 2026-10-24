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
use App\ValueObject\Money;
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
            $accountOperation = $operation instanceof Patch
                ? $this->update($this->loadExisting($uriVariables), $data)
                : $this->create($data);
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        $this->accountOperationRepository->save($accountOperation, flush: true);

        return AccountOperationResource::fromEntity($accountOperation);
    }

    private function create(AccountOperationResource $data): AccountOperation
    {
        $account = $this->resolveAccount($data->accountId);
        $currency = $account->getCurrency();

        return new AccountOperation(
            $account,
            $this->contractorResolver->resolve($data->contractorId, $data->contractorName),
            $this->parseOperationDate($data->operationDate),
            $this->parseAmount($data->amount, $currency->getCode()),
            $currency,
            description: $data->description,
            confirmed: $data->confirmed,
        );
    }

    private function update(AccountOperation $accountOperation, AccountOperationResource $data): AccountOperation
    {
        $contractor = $this->contractorResolver->resolveForUpdate(
            $data->contractorId,
            $data->contractorName,
            $accountOperation->getContractor(),
        );
        $operationDate = $this->parseOperationDate($data->operationDate);
        $amount = $this->parseAmount($data->amount, $accountOperation->getCurrency()->getCode());

        // setAmount() is the only setter that can refuse a value, so it goes first: a rejected amount
        // must leave the operation untouched.
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

        return Money::fromDecimalString($amount, $currencyCode)->getMinorUnits();
    }

    private function parseOperationDate(?string $operationDate): \DateTimeImmutable
    {
        if (null === $operationDate) {
            throw new UnprocessableEntityHttpException('Operation date is required.');
        }

        try {
            return new \DateTimeImmutable($operationDate);
        } catch (\Exception) {
            throw new UnprocessableEntityHttpException('Invalid operation date format.');
        }
    }
}
