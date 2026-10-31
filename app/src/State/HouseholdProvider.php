<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\HouseholdResource;
use App\Exception\RecordNotFoundException;
use App\Repository\HouseholdRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
     * @return HouseholdResource|list<HouseholdResource>
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): HouseholdResource|array {
        if (isset($uriVariables['id'])) {
            $household = $this->householdRepository->findByIdOrNull($uriVariables['id']);

            if (!$household) {
                $exception = RecordNotFoundException::household();

                throw new NotFoundHttpException($exception->getMessage(), $exception);
            }

            return HouseholdResource::fromEntity($household);
        }

        $households = $this->householdRepository->findBy([], ['name' => 'ASC']);

        return array_map(HouseholdResource::fromEntity(...), $households);
    }
}
