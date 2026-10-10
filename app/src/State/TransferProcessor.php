<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\TransferResource;
use App\Constant\Contractors;
use App\Entity\Account;
use App\Entity\Contractor;
use App\Repository\AccountRepository;
use App\Repository\ContractorRepository;
use App\Service\TransactionService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<mixed, TransferResource>
 */
final readonly class TransferProcessor implements ProcessorInterface
{
    public function __construct(
        private AccountRepository $accountRepository,
        private ContractorRepository $contractorRepository,
        private TransactionService $transactionService,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TransferResource
    {
        if (!$data instanceof TransferResource) {
            throw new \InvalidArgumentException('Expected instance of TransferResource.');
        }

        $fromAccount = $this->resolveAccount($data->fromAccountId, 'fromAccountId');
        $toAccount = $this->resolveAccount($data->toAccountId, 'toAccountId');
        $contractor = $this->resolveFixedContractor();
        $operationDate = $this->parseOperationDate($data->operationDate);
        $amountFrom = $this->requireAmount($data->amountFrom, 'amountFrom');

        $result = $this->performTransfer($fromAccount, $toAccount, $contractor, $operationDate, $amountFrom, $data->amountTo, $data->description);

        return TransferResource::fromOperations($result['out'], $result['in']);
    }

    /**
     * @return array{out: \App\Entity\AccountOperation, in: \App\Entity\AccountOperation}
     */
    private function performTransfer(
        Account $fromAccount,
        Account $toAccount,
        Contractor $contractor,
        \DateTimeImmutable $operationDate,
        string $amountFrom,
        ?string $amountTo,
        ?string $description,
    ): array {
        try {
            return $this->transactionService->transferBetweenAccounts(
                $fromAccount,
                $toAccount,
                $contractor,
                $operationDate,
                $amountFrom,
                $amountTo,
                $description,
            );
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
    }

    private function resolveAccount(?int $accountId, string $field): Account
    {
        if (null === $accountId) {
            throw new UnprocessableEntityHttpException(sprintf('%s is required.', $field));
        }

        $account = $this->accountRepository->findByIdOrNull($accountId);
        if (!$account) {
            throw new NotFoundHttpException(sprintf('Account referenced by %s not found.', $field));
        }

        return $account;
    }

    private function resolveFixedContractor(): Contractor
    {
        return $this->contractorRepository->findOrCreateByName(Contractors::SELF_TRANSFER_NAME);
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

    private function requireAmount(?string $amount, string $field): string
    {
        $trimmed = trim((string) $amount);
        if ('' === $trimmed) {
            throw new UnprocessableEntityHttpException(sprintf('%s is required.', $field));
        }

        return $trimmed;
    }
}
