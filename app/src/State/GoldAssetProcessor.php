<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\GoldAssetResource;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Repository\GoldAssetRepository;
use App\ValueObject\Quantity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<mixed, ?GoldAssetResource>
 */
final readonly class GoldAssetProcessor implements ProcessorInterface
{
    public function __construct(
        private GoldAssetRepository $goldAssetRepository,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?GoldAssetResource
    {
        if ($operation instanceof Delete) {
            // No 409 needed: the only FK to gold_asset (gold_price) is ON DELETE CASCADE.
            $this->goldAssetRepository->remove($this->loadExisting($uriVariables), flush: true);

            return null;
        }

        if (!$data instanceof GoldAssetResource) {
            throw new \InvalidArgumentException('Expected instance of GoldAssetResource.');
        }

        $form = $this->resolveGoldForm($data->formId);

        try {
            $goldAsset = new GoldAsset($form, $this->parseOunces($data->ounces), (string) $data->description);
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
        $id = $uriVariables['id'] ?? null;
        $goldAsset = is_numeric($id) ? $this->goldAssetRepository->find((int) $id) : null;

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

        $form = $this->em->getRepository(GoldForm::class)->find($formId);
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
