<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\Household;
use App\Service\TransactionService;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TransactionServiceTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private TransactionService $transactionService;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var TransactionService $transactionService */
        $transactionService = self::getContainer()->get(TransactionService::class);
        $this->transactionService = $transactionService;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    #[DataProvider('ignoredAmountsTo')]
    public function testTransferSameCurrencyIgnoresAmountTo(string $amountTo): void
    {
        $result = $this->transactionService->transferBetweenAccounts(
            $this->createAccount('PLN'),
            $this->createAccount('PLN'),
            $this->createContractor(),
            new DateTimeImmutable(),
            '100.00',
            $amountTo,
            null,
        );

        self::assertSame(-10000, $result['out']->getAmount());
        self::assertSame(10000, $result['in']->getAmount());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ignoredAmountsTo(): iterable
    {
        yield 'different amount' => ['2.00'];
        yield 'negative' => ['-100.00'];
        yield 'zero' => ['0.00'];
        yield 'not a number' => ['abc'];
    }

    public function testTransferSameCurrencyUsesAmountFromOnBothSides(): void
    {
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('PLN');
        $contractor = $this->createContractor();

        $result = $this->transactionService->transferBetweenAccounts(
            $from,
            $to,
            $contractor,
            new DateTimeImmutable(),
            '100.00',
            null,
            'test transfer'
        );

        self::assertSame(-10000, $result['out']->getAmount());
        self::assertSame(10000, $result['in']->getAmount());
        self::assertSame('PLN', $result['out']->getCurrency()->getCode());
        self::assertSame('PLN', $result['in']->getCurrency()->getCode());
        self::assertSame($from, $result['out']->getAccount());
        self::assertSame($to, $result['in']->getAccount());
    }

    public function testTransferDifferentCurrenciesUsesAmountToForCreditSide(): void
    {
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('CHF');
        $contractor = $this->createContractor();

        $result = $this->transactionService->transferBetweenAccounts(
            $from,
            $to,
            $contractor,
            new DateTimeImmutable(),
            '100.00',
            '25.00',
            null
        );

        self::assertSame(-10000, $result['out']->getAmount());
        self::assertSame(2500, $result['in']->getAmount());
        self::assertSame('PLN', $result['out']->getCurrency()->getCode(), 'Debit side must stay in the source account currency.');
        self::assertSame('CHF', $result['in']->getCurrency()->getCode(), 'Credit side must be in the destination account currency, not the source.');
    }

    public function testTransferReverseDirectionChfToPlnAssignsCorrectCurrenciesAndAmounts(): void
    {
        $from = $this->createAccount('CHF');
        $to = $this->createAccount('PLN');
        $contractor = $this->createContractor();

        $result = $this->transactionService->transferBetweenAccounts(
            $from,
            $to,
            $contractor,
            new DateTimeImmutable(),
            '50.00',
            '215.00',
            null
        );

        self::assertSame(-5000, $result['out']->getAmount());
        self::assertSame(21500, $result['in']->getAmount());
        self::assertSame('CHF', $result['out']->getCurrency()->getCode());
        self::assertSame('PLN', $result['in']->getCurrency()->getCode());
    }

    #[DataProvider('provideCurrencyExchangeAmounts')]
    public function testTransferDifferentCurrenciesPreservesPrecisionForProblematicAmounts(
        string $amountFrom,
        string $amountTo,
        int $expectedOutMinorUnits,
        int $expectedInMinorUnits,
    ): void {
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('CHF');
        $contractor = $this->createContractor();

        $result = $this->transactionService->transferBetweenAccounts(
            $from,
            $to,
            $contractor,
            new DateTimeImmutable(),
            $amountFrom,
            $amountTo,
            null
        );

        self::assertSame($expectedOutMinorUnits, $result['out']->getAmount());
        self::assertSame($expectedInMinorUnits, $result['in']->getAmount());
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int, 3: int}>
     */
    public static function provideCurrencyExchangeAmounts(): array
    {
        return [
            'round tenths' => ['0.10', '0.02', -10, 2],
            'classic float trap 0.1+0.2' => ['0.30', '0.07', -30, 7],
            'penny amount' => ['0.01', '0.01', -1, 1],
            'large amount near upper bound' => ['999999.99', '250000.00', -99999999, 25000000],
            'repeating decimal source' => ['33.33', '8.33', -3333, 833],
        ];
    }

    public function testTransferGeneratesSameTransactionUuidForBothOperations(): void
    {
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('PLN');
        $contractor = $this->createContractor();

        $result = $this->transactionService->transferBetweenAccounts(
            $from,
            $to,
            $contractor,
            new DateTimeImmutable(),
            '50.00',
            null,
            null
        );

        self::assertNotNull($result['out']->getTransactionUuid());
        self::assertSame($result['out']->getTransactionUuid(), $result['in']->getTransactionUuid());
    }

    public function testTransferThrowsWhenAmountFromIsNotNumeric(): void
    {
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('PLN');
        $contractor = $this->createContractor();

        $this->expectException(InvalidArgumentException::class);

        $this->transactionService->transferBetweenAccounts(
            $from,
            $to,
            $contractor,
            new DateTimeImmutable(),
            'not-a-number',
            null,
            null
        );
    }

    public function testTransferThrowsWhenAmountToIsNotNumeric(): void
    {
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('CHF');
        $contractor = $this->createContractor();

        $this->expectException(InvalidArgumentException::class);

        $this->transactionService->transferBetweenAccounts(
            $from,
            $to,
            $contractor,
            new DateTimeImmutable(),
            '100.00',
            'not-a-number',
            null
        );
    }

    public function testTransferThrowsWhenDifferentCurrenciesAndAmountToMissing(): void
    {
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('CHF');
        $contractor = $this->createContractor();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('For different currencies amountTo is required.');

        $this->transactionService->transferBetweenAccounts(
            $from,
            $to,
            $contractor,
            new DateTimeImmutable(),
            '100.00',
            null,
            null
        );
    }

    public function testTransferThrowsWhenFromAndToAreTheSameAccount(): void
    {
        $account = $this->createAccount('PLN');
        $contractor = $this->createContractor();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot transfer between the same account.');

        $this->transactionService->transferBetweenAccounts(
            $account,
            $account,
            $contractor,
            new DateTimeImmutable(),
            '100.00',
            null,
            null
        );
    }

    public function testTransferThrowsWhenAmountFromIsZero(): void
    {
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('PLN');
        $contractor = $this->createContractor();

        $this->expectException(InvalidArgumentException::class);

        $this->transactionService->transferBetweenAccounts(
            $from,
            $to,
            $contractor,
            new DateTimeImmutable(),
            '0.00',
            null,
            null
        );
    }

    public function testTransferRollsBackBothOperationsWhenAmountIsZero(): void
    {
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('PLN');
        $contractor = $this->createContractor();

        try {
            $this->transactionService->transferBetweenAccounts(
                $from,
                $to,
                $contractor,
                new DateTimeImmutable(),
                '0.00',
                null,
                null
            );
        } catch (InvalidArgumentException) {
            // expected
        }

        $operations = $this->em()->getRepository(\App\Entity\AccountOperation::class)->findAll();
        self::assertCount(0, $operations, 'No operation should be persisted when the transfer fails.');
    }

    public function testTransferredOperationsAreUnconfirmedByDefault(): void
    {
        $from = $this->createAccount('PLN');
        $to = $this->createAccount('PLN');
        $contractor = $this->createContractor();

        $result = $this->transactionService->transferBetweenAccounts(
            $from,
            $to,
            $contractor,
            new DateTimeImmutable(),
            '10.00',
            null,
            null
        );

        self::assertFalse($result['out']->isConfirmed());
        self::assertFalse($result['in']->isConfirmed());
    }

    #[DataProvider('provideNonPositiveTransferAmounts')]
    public function testNonPositiveAmountsAreRejectedWithoutPersistingOperations(
        string $toCurrency,
        string $amountFrom,
        ?string $amountTo,
    ): void {
        $from = $this->createAccount('PLN');
        $to = $this->createAccount($toCurrency);
        $contractor = $this->createContractor();
        $operations = $this->em()->getRepository(AccountOperation::class);
        $countBefore = $operations->count([]);

        try {
            $this->transactionService->transferBetweenAccounts(
                $from, $to, $contractor, new DateTimeImmutable(), $amountFrom, $amountTo, null
            );
            self::fail('A non-positive transfer amount must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Transfer amounts must be positive.', $exception->getMessage());
        }

        // A later flush must not save either side of the rejected transfer.
        self::assertTrue($this->em()->isOpen());
        $this->em()->flush();
        self::assertSame($countBefore, $operations->count([]));
    }

    /**
     * @return array<string, array{string, string, ?string}>
     */
    public static function provideNonPositiveTransferAmounts(): array
    {
        return [
            'negative debit with implicit credit' => ['PLN', '-100.00', null],
            'negative debit with explicit credit' => ['PLN', '-100.00', '100.00'],
            'negative debit in exchange' => ['CHF', '-100.00', '25.00'],
            'negative credit in exchange' => ['CHF', '100.00', '-25.00'],
            'negative debit and credit in exchange' => ['CHF', '-100.00', '-25.00'],
            'zero debit' => ['PLN', '0.00', null],
            'zero credit in exchange' => ['CHF', '100.00', '0.00'],
            'debit rounded to zero' => ['PLN', '0.004', null],
            'credit rounded to zero' => ['CHF', '100.00', '0.004'],
            'credit' => ['CHF', '100.00', '0.004'],
            'negative debit rounded to zero' => ['PLN', '-0.004', null],
        ];
    }

    private function createAccount(string $currencyCode): Account
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);

        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->findOneBy(['code' => $currencyCode]);
        if ($currency === null) {
            $currency = new Currency($currencyCode);
            $this->em()->persist($currency);
        }

        $accountType = new AccountType('Test Type ' . uniqid());
        $this->em()->persist($accountType);

        $household = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        if (null === $household) {
            throw new RuntimeException('Fixtures must provide exactly one own-funds household.');
        }

        $account = new Account($bank, $currency, $accountType, 'ACC-' . uniqid(), $household);
        $this->em()->persist($account);

        $this->em()->flush();

        return $account;
    }

    private function createContractor(): Contractor
    {
        $contractor = new Contractor('Test Contractor ' . uniqid());
        $this->em()->persist($contractor);
        $this->em()->flush();

        return $contractor;
    }
}
