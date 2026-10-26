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
final readonly class CurrencyExchangeRateProvider implements ProviderInterface
{
    public function __construct(private CurrencyExchangeRateRepository $currencyExchangeRateRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return CurrencyExchangeRateResource|list<CurrencyExchangeRateResource>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CurrencyExchangeRateResource|array|null
    {
        if (isset($uriVariables['id'])) {
            $rate = $this->currencyExchangeRateRepository->findByIdOrNull($uriVariables['id']);

            return $rate ? CurrencyExchangeRateResource::fromEntity($rate) : null;
        }

        $rates = $this->currencyExchangeRateRepository->findDataForList();

        return array_values(array_map(CurrencyExchangeRateResource::fromEntity(...), $rates));
    }
}
