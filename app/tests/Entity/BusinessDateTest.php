<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Entity\GoldPrice;
use App\Entity\Household;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BusinessDateTest extends TestCase
{
    /**
     * @param class-string<AccountOperation|GoldPrice|CurrencyExchangeRate> $entityClass
     */
    #[DataProvider('dates')]
    public function testConstructorsEnforceTheDateRange(string $entityClass, string $value, bool $valid): void
    {
        $date = new \DateTimeImmutable($value);

        if (!$valid) {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Date must be between 2025-01-01 and 2070-12-31 (inclusive).');
        }

        $entity = $this->createEntity($entityClass, $date);
        $storedDate = match (true) {
            $entity instanceof AccountOperation => $entity->getOperationDate(),
            $entity instanceof GoldPrice => $entity->getPriceDate(),
            $entity instanceof CurrencyExchangeRate => $entity->getRateDate(),
        };

        self::assertSame($date->format('Y-m-d'), $storedDate->format('Y-m-d'));
        self::assertSame('00:00:00', $storedDate->format('H:i:s'));
    }

    #[DataProvider('invalidDates')]
    public function testRejectedDateUpdateLeavesTheOperationUnchanged(string $value): void
    {
        $date = new \DateTimeImmutable('2026-01-15');
        $operation = $this->createEntity(AccountOperation::class, $date);
        self::assertInstanceOf(AccountOperation::class, $operation);
        $storedDate = $operation->getOperationDate();

        try {
            $operation->setOperationDate(new \DateTimeImmutable($value));
            self::fail('An unsupported date must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Date must be between 2025-01-01 and 2070-12-31 (inclusive).', $exception->getMessage());
        }

        self::assertSame($storedDate, $operation->getOperationDate());
    }

    /**
     * @return iterable<string, array{class-string<AccountOperation|GoldPrice|CurrencyExchangeRate>, string, bool}>
     */
    public static function dates(): iterable
    {
        foreach ([AccountOperation::class, GoldPrice::class, CurrencyExchangeRate::class] as $entityClass) {
            foreach ([
                'first day' => '2025-01-01',
                'end of last day' => '2070-12-31 23:59:59',
                'leap day' => '2028-02-29',
            ] as $case => $date) {
                yield $entityClass.' / '.$case => [$entityClass, $date, true];
            }

            foreach (self::invalidDates() as $case => [$date]) {
                yield $entityClass.' / '.$case => [$entityClass, $date, false];
            }
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDates(): array
    {
        return [
            'before first day' => ['2024-12-31 23:59:59'],
            'after last day' => ['2071-01-01'],
        ];
    }

    /**
     * @param class-string<AccountOperation|GoldPrice|CurrencyExchangeRate> $entityClass
     */
    private function createEntity(
        string $entityClass,
        \DateTimeImmutable $date
    ): AccountOperation|GoldPrice|CurrencyExchangeRate
    {
        $currency = new Currency('PLN');
        $bank = new Bank('Test Bank');

        return match ($entityClass) {
            AccountOperation::class => new AccountOperation(
                new Account(
                    $bank,
                    $currency,
                    new AccountType('Test'),
                    'TEST-1',
                    new Household('Test')
                ),
                new Contractor('Test contractor'),
                $date,
                1000,
                $currency,
            ),
            GoldPrice::class => new GoldPrice(
                new GoldAsset(
                    new GoldForm('Coin'),
                    10000,
                    'Gold'
                ),
                $date,
                1000,
                $currency
            ),
            CurrencyExchangeRate::class => new CurrencyExchangeRate(
                $bank,
                new Currency('CHF'),
                $date,
                43210
            ),
            default => throw new \InvalidArgumentException('Unknown entity: '.$entityClass),
        };
    }
}
