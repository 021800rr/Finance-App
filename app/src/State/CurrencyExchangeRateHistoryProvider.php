<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CurrencyExchangeRateResource;
use App\Repository\CurrencyExchangeRateRepository;

/**
 * @implements ProviderInterface<CurrencyExchangeRateResource>
 */
final readonly class CurrencyExchangeRateHistoryProvider implements ProviderInterface
{
    public function __construct(private CurrencyExchangeRateRepository $currencyExchangeRateRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<CurrencyExchangeRateResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $rates = $this->currencyExchangeRateRepository->findAllOrderedForChart();

        return array_values(array_map(CurrencyExchangeRateResource::fromEntity(...), $rates));
    }
}
