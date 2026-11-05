<?php

declare(strict_types=1);

namespace App\Tests\Database;

use App\Entity\Account;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The entities refuse these values, but the database must refuse them too, for writes that bypass the entities
 * (manual SQL, a script, a future bug). Each case changes a valid row with plain SQL.
 */
final class ConstraintTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

    protected function setUp(): void
    {
        $this->initEntityManager(self::bootKernel());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    #[DataProvider('acceptedChanges')]
    public function testTheDatabaseAcceptsTheLimitsThemselves(string $sql): void
    {
        $ids = $this->createRows();

        self::assertSame(1, $this->em()->getConnection()->executeStatement($sql, $ids));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedChanges(): iterable
    {
        yield 'operation amount at the maximum' => [
            'UPDATE account_operation SET amount_minor_units = 99999999 WHERE id = :operation',
        ];
        yield 'operation amount at the minimum' => [
            'UPDATE account_operation SET amount_minor_units = -99999999 WHERE id = :operation',
        ];
        yield 'operation on the first business day' => [
            "UPDATE account_operation SET operation_date = '2025-01-01' WHERE id = :operation",
        ];
        yield 'gold price on the last business day' => [
            "UPDATE gold_price SET price_date = '2070-12-31' WHERE id = :price",
        ];
        yield 'exchange rate on the last business day' => [
            "UPDATE currency_exchange_rate SET rate_date = '2070-12-31' WHERE id = :rate",
        ];
        yield 'operation moved to another account in the same currency' => [
            'UPDATE account_operation SET account_id = :otherPlnAccount WHERE id = :operation',
        ];
        yield 'currency of an account without operations' => [
            "UPDATE account SET currency_code = 'CHF' WHERE id = :emptyAccount",
        ];
    }

    /**
     * @return array<string, int>
     */
    private function createRows(): array
    {
        $plnAccount = $this->account('Bank1-ROR-PLN');
        $day = new \DateTimeImmutable('2026-01-15');
        $operation = $this->createOperation(
            $plnAccount,
            -1000,
            $day,
            $this->createContractor('Constraint test '.uniqid()),
        );
        $price = $this->createGoldPrice($this->createGoldAsset(description: 'Constraint test gold'), $day);
        $rate = $this->createRate(date: $day);
        $emptyAccount = $this->createAccount(
            type: $plnAccount->getType(),
            household: $plnAccount->getHousehold(),
            accountNumber: 'CONSTRAINT-'.uniqid(),
        );

        return [
            'operation' => $operation->getId(),
            'price' => $price->getId(),
            'rate' => $rate->getId(),
            'account' => $plnAccount->getId(),
            'emptyAccount' => $emptyAccount->getId(),
            'otherPlnAccount' => $this->account('Bank1-WAK-PLN')->getId(),
            'chfAccount' => $this->account('Bank1-WAK-CHF')->getId(),
        ];
    }

    private function account(string $number): Account
    {
        $account = $this->em()->getRepository(Account::class)->findOneBy(['accountNumber' => $number]);
        self::assertNotNull($account);

        return $account;
    }
}
