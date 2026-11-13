<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AccountResource;
use App\ApiResource\Input\AccountInput;
use App\Entity\Account;
use App\Exception\RecordNotFoundException;
use App\Repository\AccountRepository;
use App\Service\AccountOperationSummaryService;

/**
 * @implements ProviderInterface<AccountResource|AccountInput>
 */
final readonly class AccountProvider implements ProviderInterface
{
    public function __construct(
        private AccountRepository $accountRepository,
        private AccountOperationSummaryService $summaryService,
    ) {
    }

    /**
     * The list gives the accounts without balances. GET of one account answers with its balances; PATCH gets the
     * current values as AccountInput and lays the request over them.
     *
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return AccountResource|AccountInput|list<AccountResource>
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): AccountResource|AccountInput|array {
        if ($operation instanceof CollectionOperationInterface) {
            /** @var list<Account> $accounts */
            $accounts = $this->accountRepository->createOrderedForSelectQueryBuilder()->getQuery()->getResult();

            return array_map(AccountResource::fromEntity(...), $accounts);
        }

        $account = $this->accountRepository->findByIdOrNull($uriVariables['id'] ?? null);
        if (null === $account) {
            throw RecordNotFoundException::account()->asHttp();
        }

        if (!$operation instanceof Get) {
            return AccountInput::fromEntity($account);
        }

        $balances = $this->summaryService->getBalances($account);

        return AccountResource::fromEntity($account)
            ->withBalances($balances['saldoAll'], $balances['saldoToday'], $balances['showSaldoToday']);
    }
}
