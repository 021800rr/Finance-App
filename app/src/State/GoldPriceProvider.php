<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\GoldPriceResource;
use App\Exception\RecordNotFoundException;
use App\Repository\GoldPriceRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
     * @return GoldPriceResource|list<GoldPriceResource>
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): GoldPriceResource|array {
        if (isset($uriVariables['id'])) {
            $goldPrice = $this->goldPriceRepository->findByIdOrNull($uriVariables['id']);

            if (!$goldPrice) {
                $exception = RecordNotFoundException::goldPrice();

                throw new NotFoundHttpException($exception->getMessage(), $exception);
            }

            return GoldPriceResource::fromEntity($goldPrice);
        }

        $goldPrices = $this->goldPriceRepository->findDataForList();

        return array_values(array_map(GoldPriceResource::fromEntity(...), $goldPrices));
    }
}
