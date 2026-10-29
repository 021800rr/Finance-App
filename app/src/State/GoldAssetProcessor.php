<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\GoldAssetResource;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Repository\GoldAssetRepository;
use App\Repository\GoldFormRepository;
use App\ValueObject\Quantity;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<mixed, ?GoldAssetResource>
 */
final readonly class GoldAssetProcessor implements ProcessorInterface
{
    public function __construct(
        private GoldAssetRepository $goldAssetRepository,
        private GoldFormRepository $goldFormRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ?GoldAssetResource {
        if ($operation instanceof Delete) {
            // No 409 needed: the only FK to gold_asset (gold_price) is ON DELETE CASCADE.
            $this->goldAssetRepository->remove($this->loadExisting($uriVariables), flush: true);

            return null;
        }

        if (!$data instanceof GoldAssetResource) {
            throw new \InvalidArgumentException('Expected instance of GoldAssetResource.');
        }

        $goldAsset = $operation instanceof Patch ? $this->loadExisting($uriVariables) : null;
        $form = $this->resolveGoldForm($data->formId);
        $ounces = $this->parseOunces($data->ounces);
        $description = trim((string) $data->description);

        try {
            if (null === $goldAsset) {
                $goldAsset = new GoldAsset($form, $ounces, $description);
            } else {
                $goldAsset->setOunces($ounces);
                if ($data->description !== $goldAsset->getDescription()) {
                    $goldAsset->setDescription($description);
                }
                $goldAsset->setForm($form);
            }
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        $this->goldAssetRepository->save($goldAsset, flush: true);

        return GoldAssetResource::fromEntity($goldAsset);
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function loadExisting(array $uriVariables): GoldAsset
    {
        $goldAsset = $this->goldAssetRepository->findByIdOrNull($uriVariables['id'] ?? null);

        if (!$goldAsset) {
            throw new NotFoundHttpException('Gold asset not found.');
        }

        return $goldAsset;
    }

    private function resolveGoldForm(?int $formId): GoldForm
    {
        if (null === $formId) {
            throw new UnprocessableEntityHttpException('formId is required.');
        }

        $form = $this->goldFormRepository->findByIdOrNull($formId);
        if (!$form) {
            throw new NotFoundHttpException('Gold form not found.');
        }

        return $form;
    }

    private function parseOunces(?string $ounces): int
    {
        $trimmed = trim((string) $ounces);
        if ('' === $trimmed) {
            throw new UnprocessableEntityHttpException('Ounces is required.');
        }

        try {
            return Quantity::fromDecimalString($trimmed)->getMinorUnits();
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
    }
}
