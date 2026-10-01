<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CurrencyResource;
use App\Repository\CurrencyRepository;

/**
 * @implements ProviderInterface<CurrencyResource>
 */
final readonly class CurrencyProvider implements ProviderInterface
{
    public function __construct(private CurrencyRepository $currencyRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return CurrencyResource|list<CurrencyResource>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CurrencyResource|array|null
    {
        if (isset($uriVariables['code'])) {
            $currency = $this->currencyRepository->find($uriVariables['code']);

            return $currency ? CurrencyResource::fromEntity($currency) : null;
        }

        $currencies = $this->currencyRepository->findBy([], ['code' => 'ASC']);

        return array_map(CurrencyResource::fromEntity(...), $currencies);
    }
}
