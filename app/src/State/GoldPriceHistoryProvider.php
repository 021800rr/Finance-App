<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\GoldPriceResource;
use App\Repository\GoldPriceRepository;
use Doctrine\DBAL\Exception;

/**
 * @implements ProviderInterface<GoldPriceResource>
 */
final readonly class GoldPriceHistoryProvider implements ProviderInterface
{
    public function __construct(private GoldPriceRepository $goldPriceRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<GoldPriceResource>
     *
     * @throws Exception
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $prices = $this->goldPriceRepository->findDataForChart();

        return array_map(GoldPriceResource::fromRow(...), $prices);
    }
}
