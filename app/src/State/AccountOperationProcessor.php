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
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?AccountOperationResource
    {
        if ($operation instanceof Delete) {
            $this->accountOperationRepository->remove($this->loadExisting($uriVariables), flush: true);

            return null;
        }

        if (!$data instanceof AccountOperationResource) {
            throw new \InvalidArgumentException('Expected instance of AccountOperationResource.');
        }

        $accountOperation = $operation instanceof Patch ? $this->loadExisting($uriVariables) : null;

        $account = $this->resolveAccount($data->accountId);
        $contractor = $this->contractorResolver->resolve($data->contractorId, $data->contractorName);
        $operationDate = $this->parseOperationDate($data->operationDate);
        $amountMinorUnits = Money::fromDecimalString((string) $data->amount, $account->getCurrency()->getCode())->getMinorUnits();

        if (null === $accountOperation) {
            $accountOperation = new AccountOperation($account, $contractor, $operationDate, $amountMinorUnits, $account->getCurrency());
        } else {
            $accountOperation->setAccount($account);
            $accountOperation->setContractor($contractor);
            $accountOperation->setOperationDate($operationDate);
            $accountOperation->setAmount($amountMinorUnits);
            $accountOperation->setCurrency($account->getCurrency());
        }

        $accountOperation->setDescription($data->description);
        $accountOperation->setConfirmed($data->confirmed);

        $this->accountOperationRepository->save($accountOperation, flush: true);

        return AccountOperationResource::fromEntity($accountOperation);
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
