<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CurrencyExchangeRateResource;
use App\Exception\RecordNotFoundException;
use App\Repository\CurrencyExchangeRateRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
     * @return CurrencyExchangeRateResource|list<CurrencyExchangeRateResource>
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): CurrencyExchangeRateResource|array {
        if (isset($uriVariables['id'])) {
            $rate = $this->currencyExchangeRateRepository->findByIdOrNull($uriVariables['id']);

            if (!$rate) {
                $exception = RecordNotFoundException::currencyExchangeRate();

                throw new NotFoundHttpException($exception->getMessage(), $exception);
            }

            return CurrencyExchangeRateResource::fromEntity($rate);
        }

        $rates = $this->currencyExchangeRateRepository->findDataForList();

        return array_values(array_map(CurrencyExchangeRateResource::fromEntity(...), $rates));
    }
}
