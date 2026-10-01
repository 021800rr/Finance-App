<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BankResource;
use App\Entity\Bank;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProviderInterface<BankResource>
 */
final readonly class BankProvider implements ProviderInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return BankResource|list<BankResource>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BankResource|array|null
    {
        $repository = $this->em->getRepository(Bank::class);

        if (isset($uriVariables['id'])) {
            $bank = $repository->find($uriVariables['id']);

            return $bank ? BankResource::fromEntity($bank) : null;
        }

        $banks = $repository->findBy([], ['name' => 'ASC']);

        return array_map(BankResource::fromEntity(...), $banks);
    }
}
