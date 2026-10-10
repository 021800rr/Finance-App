<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\GoldPriceResource;
use App\Repository\GoldPriceRepository;

/**
 * @implements ProviderInterface<GoldPriceResource>
 */
final readonly class GoldPriceProvider implements ProviderInterface
{
    public function __construct(private GoldPriceRepository $goldPriceRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return GoldPriceResource|list<GoldPriceResource>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): GoldPriceResource|array|null
    {
        if (isset($uriVariables['id'])) {
            $goldPrice = $this->goldPriceRepository->findByIdOrNull($uriVariables['id']);

            return $goldPrice ? GoldPriceResource::fromEntity($goldPrice) : null;
        }

        $goldPrices = $this->goldPriceRepository->findLatestOrdered();

        return array_values(array_map(GoldPriceResource::fromEntity(...), $goldPrices));
    }
}
