<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CurrencyResource;
use App\Exception\RecordNotFoundException;
use App\Repository\CurrencyRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
     * @return CurrencyResource|list<CurrencyResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CurrencyResource|array
    {
        if (isset($uriVariables['code'])) {
            $currency = $this->currencyRepository->find($uriVariables['code']);

            if (!$currency) {
                $exception = RecordNotFoundException::currency();

                throw new NotFoundHttpException($exception->getMessage(), $exception);
            }

            return CurrencyResource::fromEntity($currency);
        }

        $currencies = $this->currencyRepository->findBy([], ['code' => 'ASC']);

        return array_map(CurrencyResource::fromEntity(...), $currencies);
    }
}
