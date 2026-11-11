<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\GoldAssetResource;
use App\ApiResource\Input\GoldAssetInput;
use App\Exception\RecordNotFoundException;
use App\Repository\GoldAssetRepository;

/**
 * @implements ProviderInterface<GoldAssetResource|GoldAssetInput>
 */
final readonly class GoldAssetProvider implements ProviderInterface
{
    public function __construct(private GoldAssetRepository $goldAssetRepository)
    {
    }

    /**
     * GET answers with GoldAssetResource. PATCH and DELETE get the current values as GoldAssetInput: PATCH lays the
     * request over them (what it leaves out stays), and the processor reads the record they belong to.
     *
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return GoldAssetResource|GoldAssetInput|list<GoldAssetResource>
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): GoldAssetResource|GoldAssetInput|array {
        if (isset($uriVariables['id'])) {
            $goldAsset = $this->goldAssetRepository->findByIdOrNull($uriVariables['id']);

            if (!$goldAsset) {
                throw RecordNotFoundException::goldAsset()->asHttp();
            }

            return $operation instanceof Get
                ? GoldAssetResource::fromEntity($goldAsset)
                : GoldAssetInput::fromEntity($goldAsset);
        }

        $goldAssets = $this->goldAssetRepository->findAllOrdered();

        return array_values(array_map(GoldAssetResource::fromEntity(...), $goldAssets));
    }
}
