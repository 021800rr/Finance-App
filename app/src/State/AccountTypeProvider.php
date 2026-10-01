<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AccountTypeResource;
use App\Entity\AccountType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProviderInterface<AccountTypeResource>
 */
final readonly class AccountTypeProvider implements ProviderInterface
{
    public function __construct(private EntityManagerInterface $em)
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
        $repository = $this->em->getRepository(AccountType::class);

        if (isset($uriVariables['id'])) {
            $accountType = $repository->find($uriVariables['id']);

            return $accountType ? AccountTypeResource::fromEntity($accountType) : null;
        }

        $accountTypes = $repository->findBy([], ['name' => 'ASC']);

        return array_map(AccountTypeResource::fromEntity(...), $accountTypes);
    }
}
