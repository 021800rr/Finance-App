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
     * @return list<CurrencyResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $currencies = $this->currencyRepository->findBy([], ['code' => 'ASC']);

        return array_map(CurrencyResource::fromEntity(...), $currencies);
    }
}
