<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\GoldAssetResource;
use App\Repository\GoldAssetRepository;

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
     * @return GoldAssetResource|list<GoldAssetResource>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): GoldAssetResource|array|null
    {
        if (isset($uriVariables['id'])) {
            $goldAsset = $this->goldAssetRepository->findByIdOrNull($uriVariables['id']);

            return $goldAsset ? GoldAssetResource::fromEntity($goldAsset) : null;
        }

        $goldAssets = $this->goldAssetRepository->findAllOrdered();

        return array_values(array_map(GoldAssetResource::fromEntity(...), $goldAssets));
    }
}
