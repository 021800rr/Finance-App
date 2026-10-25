<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CurrencyExchangeRateResource;
use App\Constant\Currencies;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\Repository\BankRepository;
use App\Repository\CurrencyExchangeRateRepository;
use App\Util\BusinessDateRange;
use App\Util\DateParser;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<mixed, ?CurrencyExchangeRateResource>
 */
final readonly class CurrencyExchangeRateProcessor implements ProcessorInterface
{
    public function __construct(
        private BankRepository $bankRepository,
        private CurrencyExchangeRateRepository $currencyExchangeRateRepository,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?CurrencyExchangeRateResource
    {
        if ($operation instanceof Delete) {
            $this->currencyExchangeRateRepository->remove($this->loadExisting($uriVariables), flush: true);

            return null;
        }

        if (!$data instanceof CurrencyExchangeRateResource) {
            throw new \InvalidArgumentException('Expected instance of CurrencyExchangeRateResource.');
        }

        $bank = $this->resolveBank($data->bankId);
        $currency = $this->resolveCurrency($data->currencyCode);
        $rateDate = $this->parseRateDate($data->rateDate);
        $rateMinorUnits = $this->parseRate($data->rateToPln);

        try {
            $rate = new CurrencyExchangeRate($bank, $currency, $rateDate, $rateMinorUnits);
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        // Multiple rates per bank/currency/day are allowed by design: the most
        // recently added rate (highest id) is the one that counts as "current".
        $this->currencyExchangeRateRepository->save($rate, flush: true);

        return CurrencyExchangeRateResource::fromEntity($rate);
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function loadExisting(array $uriVariables): CurrencyExchangeRate
    {
        $rate = $this->currencyExchangeRateRepository->findByIdOrNull($uriVariables['id'] ?? null);

        if (!$rate) {
            throw new NotFoundHttpException('Exchange rate not found.');
        }

        return $rate;
    }

    private function resolveBank(?int $bankId): Bank
    {
        if (null === $bankId) {
            throw new UnprocessableEntityHttpException('bankId is required.');
        }

        $bank = $this->bankRepository->findByIdOrNull($bankId);
        if (!$bank) {
            throw new NotFoundHttpException('Bank not found.');
        }

        return $bank;
    }

    private function resolveCurrency(?string $currencyCode): Currency
    {
        $trimmed = trim((string) $currencyCode);
        if ('' === $trimmed) {
            throw new UnprocessableEntityHttpException('currencyCode is required.');
        }

        $currency = $this->em->getRepository(Currency::class)->find($trimmed);
        if (!$currency) {
            throw new NotFoundHttpException('Currency not found.');
        }

        return $currency;
    }

    private function parseRateDate(?string $rateDate): \DateTimeImmutable
    {
        if (null === $rateDate) {
            throw new UnprocessableEntityHttpException('rateDate is required.');
        }

        $date = DateParser::tryParse($rateDate);
        if (null === $date) {
            throw new UnprocessableEntityHttpException('Invalid rateDate format.');
        }

        if (!BusinessDateRange::contains($date)) {
            throw new UnprocessableEntityHttpException(BusinessDateRange::ERROR_MESSAGE);
        }

        return $date;
    }

    private function parseRate(?string $rateToPln): int
    {
        $trimmed = trim((string) $rateToPln);
        if ('' === $trimmed) {
            throw new UnprocessableEntityHttpException('rateToPln is required.');
        }

        try {
            return Money::fromDecimalString($trimmed, Currencies::SETTLEMENT_CURRENCY_CODE, Currencies::EXCHANGE_RATE_SCALE)
                ->getMinorUnits();
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
    }
}
