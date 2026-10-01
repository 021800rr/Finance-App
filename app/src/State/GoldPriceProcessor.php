<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\GoldPriceResource;
use App\Constant\Currencies;
use App\Entity\GoldAsset;
use App\Entity\GoldPrice;
use App\Repository\CurrencyRepository;
use App\Repository\GoldAssetRepository;
use App\Repository\GoldPriceRepository;
use App\ValueObject\Money;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<mixed, ?GoldPriceResource>
 */
final readonly class GoldPriceProcessor implements ProcessorInterface
{
    public function __construct(
        private GoldPriceRepository $goldPriceRepository,
        private GoldAssetRepository $goldAssetRepository,
        private CurrencyRepository $currencyRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?GoldPriceResource
    {
        if ($operation instanceof Delete) {
            $this->goldPriceRepository->remove($this->loadExisting($uriVariables), flush: true);

            return null;
        }

        if (!$data instanceof GoldPriceResource) {
            throw new \InvalidArgumentException('Expected instance of GoldPriceResource.');
        }

        $goldAsset = $this->resolveGoldAsset($data->goldAssetId);
        $priceDate = $this->parsePriceDate($data->priceDate);
        $pricePlnMinorUnits = $this->parsePrice($data->pricePln);

        try {
            // Always PLN: the entity's own naming (price_pln_minor_units) and this resource's
            // description both assume it, so the currency isn't a client-supplied field.
            $goldPrice = new GoldPrice($goldAsset, $priceDate, $pricePlnMinorUnits, $this->currencyRepository->findPlnOrThrow());
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        // Multiple entries per gold asset/day are allowed by design, same as CurrencyExchangeRate.
        $this->goldPriceRepository->save($goldPrice, flush: true);

        return GoldPriceResource::fromEntity($goldPrice);
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function loadExisting(array $uriVariables): GoldPrice
    {
        $id = $uriVariables['id'] ?? null;
        $goldPrice = is_numeric($id) ? $this->goldPriceRepository->find((int) $id) : null;

        if (!$goldPrice) {
            throw new NotFoundHttpException('Gold price entry not found.');
        }

        return $goldPrice;
    }

    private function resolveGoldAsset(?int $goldAssetId): GoldAsset
    {
        if (null === $goldAssetId) {
            throw new UnprocessableEntityHttpException('goldAssetId is required.');
        }

        $goldAsset = $this->goldAssetRepository->find($goldAssetId);
        if (!$goldAsset) {
            throw new NotFoundHttpException('Gold asset not found.');
        }

        return $goldAsset;
    }

    private function parsePriceDate(?string $priceDate): \DateTimeImmutable
    {
        if (null === $priceDate) {
            throw new UnprocessableEntityHttpException('priceDate is required.');
        }

        try {
            return new \DateTimeImmutable($priceDate);
        } catch (\Exception) {
            throw new UnprocessableEntityHttpException('Invalid priceDate format.');
        }
    }

    private function parsePrice(?string $pricePln): int
    {
        $trimmed = trim((string) $pricePln);
        if ('' === $trimmed) {
            throw new UnprocessableEntityHttpException('pricePln is required.');
        }

        try {
            return Money::fromDecimalString($trimmed, Currencies::SETTLEMENT_CURRENCY_CODE, Currencies::MINOR_UNIT_SCALE)
                ->getMinorUnits();
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
    }
}
