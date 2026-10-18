<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AccountResource;
use App\Entity\Account;
use App\Repository\AccountRepository;
use App\Service\AccountOperationSummaryService;

/**
 * @implements ProviderInterface<AccountResource>
 */
final readonly class AccountProvider implements ProviderInterface
{
    public function __construct(
        private AccountRepository $accountRepository,
        private AccountOperationSummaryService $summaryService,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return AccountResource|list<AccountResource>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AccountResource|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            /** @var list<Account> $accounts */
            $accounts = $this->accountRepository->createOrderedForSelectQueryBuilder()->getQuery()->getResult();

            return array_map(AccountResource::fromEntity(...), $accounts);
        }

        $account = $this->accountRepository->findByIdOrNull($uriVariables['id'] ?? null);
        if (null === $account) {
            return null;
        }

        $resource = AccountResource::fromEntity($account);

        // PATCH loads the resource through this provider too, but it neither needs nor returns balances.
        if ($operation instanceof Get) {
            $balances = $this->summaryService->getBalances($account);
            $resource->saldo = $balances['saldoAll']->toArray();
            $resource->saldoToday = $balances['saldoToday']->toArray();
            $resource->hasFutureOperations = $balances['showSaldoToday'];
        }

        return $resource;
    }
}
