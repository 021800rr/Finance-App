<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AccountResource;
use App\Repository\AccountRepository;

/**
 * @implements ProviderInterface<AccountResource>
 */
final readonly class AccountProvider implements ProviderInterface
{
    public function __construct(private AccountRepository $accountRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return AccountResource|list<AccountResource>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AccountResource|array|null
    {
        if (isset($uriVariables['id'])) {
            $account = $this->accountRepository->findByIdOrNull($uriVariables['id']);

            return $account ? AccountResource::fromEntity($account) : null;
        }

        /** @var list<\App\Entity\Account> $accounts */
        $accounts = $this->accountRepository->createOrderedForSelectQueryBuilder()->getQuery()->getResult();

        return array_map(AccountResource::fromEntity(...), $accounts);
    }
}
