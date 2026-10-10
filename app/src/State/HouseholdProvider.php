<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\HouseholdResource;
use App\Repository\HouseholdRepository;

/**
 * @implements ProviderInterface<HouseholdResource>
 */
final readonly class HouseholdProvider implements ProviderInterface
{
    public function __construct(private HouseholdRepository $householdRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return HouseholdResource|list<HouseholdResource>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): HouseholdResource|array|null
    {
        if (isset($uriVariables['id'])) {
            $household = $this->householdRepository->findByIdOrNull($uriVariables['id']);

            return $household ? HouseholdResource::fromEntity($household) : null;
        }

        $households = $this->householdRepository->findBy([], ['name' => 'ASC']);

        return array_map(HouseholdResource::fromEntity(...), $households);
    }
}
