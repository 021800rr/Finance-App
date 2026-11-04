<?php

declare(strict_types=1);

namespace App\Tests\Database;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Entity\GoldPrice;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The entities refuse these values, but the database must refuse them too, for writes that bypass the entities
 * (manual SQL, a script, a future bug). Each case changes a valid row with plain SQL.
 */
final class ConstraintTest extends KernelTestCase
{
    use DatabaseTestTrait;

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
        yield 'operation amount at the maximum' => ['UPDATE account_operation SET amount_minor_units = 99999999 WHERE id = :operation'];
        yield 'operation amount at the minimum' => ['UPDATE account_operation SET amount_minor_units = -99999999 WHERE id = :operation'];
        yield 'operation on the first business day' => ["UPDATE account_operation SET operation_date = '2025-01-01' WHERE id = :operation"];
        yield 'gold price on the last business day' => ["UPDATE gold_price SET price_date = '2070-12-31' WHERE id = :price"];
        yield 'exchange rate on the last business day' => ["UPDATE currency_exchange_rate SET rate_date = '2070-12-31' WHERE id = :rate"];
        yield 'operation moved to another account in the same currency' => ['UPDATE account_operation SET account_id = :otherPlnAccount WHERE id = :operation'];
        yield 'currency of an account without operations' => ["UPDATE account SET currency_code = 'CHF' WHERE id = :emptyAccount"];
    }

    /**
     * @return array<string, int>
     */
    private function createRows(): array
    {
        $plnAccount = $this->account('Bank1-ROR-PLN');
        $pln = $this->em()->find(Currency::class, 'PLN');
        $chf = $this->em()->find(Currency::class, 'CHF');
        $bank = $this->em()->getRepository(Bank::class)->findOneBy([]);
        $form = $this->em()->getRepository(GoldForm::class)->findOneBy([]);
        self::assertNotNull($pln);
        self::assertNotNull($chf);
        self::assertNotNull($bank);
        self::assertNotNull($form);

        $day = new \DateTimeImmutable('2026-01-15');
        $contractor = new Contractor('Constraint test '.uniqid());
        $operation = new AccountOperation($plnAccount, $contractor, $day, -1000, $pln);
        $asset = new GoldAsset($form, 10000, 'Constraint test gold');
        $price = new GoldPrice($asset, $day, 100000, $pln);
        $rate = new CurrencyExchangeRate($bank, $chf, $day, 43210);
        $emptyAccount = new Account(
            $bank,
            $pln,
            $plnAccount->getType(),
            'CONSTRAINT-'.uniqid(),
            $plnAccount->getHousehold(),
        );
        foreach ([$contractor, $operation, $asset, $price, $rate, $emptyAccount] as $entity) {
            $this->em()->persist($entity);
        }
        $this->em()->flush();

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
