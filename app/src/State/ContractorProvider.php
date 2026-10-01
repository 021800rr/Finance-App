<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ContractorResource;
use App\Repository\ContractorRepository;

/**
 * @implements ProviderInterface<ContractorResource>
 */
final readonly class ContractorProvider implements ProviderInterface
{
    public function __construct(private ContractorRepository $contractorRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return ContractorResource|list<ContractorResource>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ContractorResource|array|null
    {
        if (isset($uriVariables['id'])) {
            $contractor = $this->contractorRepository->find($uriVariables['id']);

            return $contractor ? ContractorResource::fromEntity($contractor) : null;
        }

        $contractors = $this->contractorRepository->findBy([], ['name' => 'ASC']);

        return array_map(ContractorResource::fromEntity(...), $contractors);
    }
}
