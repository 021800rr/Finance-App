<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CurrencyExchangeRateResource;
use App\Repository\CurrencyExchangeRateRepository;
use Doctrine\DBAL\Exception;

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
     *
     * @throws Exception
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $rates = $this->currencyExchangeRateRepository->findDataForChart();

        return array_map(CurrencyExchangeRateResource::fromRow(...), $rates);
    }
}
