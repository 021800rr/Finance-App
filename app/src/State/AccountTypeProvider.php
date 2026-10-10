<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AccountTypeResource;
use App\Repository\AccountTypeRepository;

/**
 * @implements ProviderInterface<AccountTypeResource>
 */
final readonly class AccountTypeProvider implements ProviderInterface
{
    public function __construct(private AccountTypeRepository $accountTypeRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return AccountTypeResource|list<AccountTypeResource>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AccountTypeResource|array|null
    {
        if (isset($uriVariables['id'])) {
            $accountType = $this->accountTypeRepository->findByIdOrNull($uriVariables['id']);

            return $accountType ? AccountTypeResource::fromEntity($accountType) : null;
        }

        $accountTypes = $this->accountTypeRepository->findBy([], ['name' => 'ASC']);

        return array_map(AccountTypeResource::fromEntity(...), $accountTypes);
    }
}
