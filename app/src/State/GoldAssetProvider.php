<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\GoldAssetResource;
use App\Exception\RecordNotFoundException;
use App\Repository\GoldAssetRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<GoldAssetResource>
 */
final readonly class GoldAssetProvider implements ProviderInterface
{
    public function __construct(private GoldAssetRepository $goldAssetRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return GoldAssetResource|list<GoldAssetResource>
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): GoldAssetResource|array {
        if (isset($uriVariables['id'])) {
            $goldAsset = $this->goldAssetRepository->findByIdOrNull($uriVariables['id']);

            if (!$goldAsset) {
                $exception = RecordNotFoundException::goldAsset();

                throw new NotFoundHttpException($exception->getMessage(), $exception);
            }

            return GoldAssetResource::fromEntity($goldAsset);
        }

        $goldAssets = $this->goldAssetRepository->findAllOrdered();

        return array_values(array_map(GoldAssetResource::fromEntity(...), $goldAssets));
    }
}
