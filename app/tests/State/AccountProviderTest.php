<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\ApiResource\AccountResource;
use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\Household;
use App\State\AccountProvider;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AccountProviderTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private AccountProvider $provider;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var AccountProvider $provider */
        $provider = self::getContainer()->get(AccountProvider::class);
        $this->provider = $provider;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProvideReturnsResourceForExistingId(): void
    {
        $account = $this->createAccount('AAA-'.uniqid());

        $result = $this->provider->provide(new Get(), ['id' => $account->getId()]);

        self::assertInstanceOf(AccountResource::class, $result);
        self::assertSame($account->getId(), $result->id);
        self::assertSame($account->getAccountNumber(), $result->accountNumber);
    }

    public function testProvideForCollectionReturnsAllAccounts(): void
    {
        $account = $this->createAccount('COL-'.uniqid());

        $result = $this->provider->provide(new GetCollection());

        self::assertIsArray($result);
        self::assertCount($this->em()->getRepository(Account::class)->count([]), $result);
        self::assertContains($account->getId(), array_map(static fn (AccountResource $resource): ?int => $resource->id, $result));
    }

    public function testProvideThrowsNotFoundForNonExistentId(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Account not found.');

        $this->provider->provide(new Get(), ['id' => 999999999]);
    }

    public function testProvideThrowsNotFoundForNonNumericId(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Account not found.');

        $this->provider->provide(new Get(), ['id' => 'not-a-number']);
    }

    public function testProvideForGetIncludesBalances(): void
    {
        $account = $this->createAccount('BAL-'.uniqid());
        $this->createOperation($account, 10000, new \DateTimeImmutable('-1 day'));
        $this->createOperation($account, 2500, new \DateTimeImmutable('+3 days'));

        $result = $this->provider->provide(new Get(), ['id' => $account->getId()]);

        self::assertInstanceOf(AccountResource::class, $result);
        self::assertSame(['amount' => '125.00', 'currency' => 'PLN'], $result->saldo);
        self::assertSame(['amount' => '100.00', 'currency' => 'PLN'], $result->saldoToday);
        self::assertTrue($result->hasFutureOperations);
    }

    public function testProvideForPatchSkipsBalances(): void
    {
        $account = $this->createAccount('BAL-'.uniqid());
        $this->createOperation($account, 10000, new \DateTimeImmutable('-1 day'));

        $result = $this->provider->provide(new Patch(), ['id' => $account->getId()]);

        self::assertInstanceOf(AccountResource::class, $result);
        self::assertNull($result->saldo);
        self::assertNull($result->saldoToday);
        self::assertNull($result->hasFutureOperations);
    }

    private function createAccount(string $accountNumber): Account
    {
        $bank = new Bank('Test Bank '.uniqid());
        $this->em()->persist($bank);

        $currency = $this->ensurePlnCurrency();

        $accountType = new AccountType('Test Type '.uniqid());
        $this->em()->persist($accountType);

        $household = $this->ownFundsHousehold();

        $account = new Account($bank, $currency, $accountType, $accountNumber, $household);
        $this->em()->persist($account);
        $this->em()->flush();

        return $account;
    }

    private function ensurePlnCurrency(): Currency
    {
        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->find('PLN');
        if (null === $currency) {
            $currency = new Currency('PLN');
            $this->em()->persist($currency);
            $this->em()->flush();
        }

        return $currency;
    }

    private function ownFundsHousehold(): Household
    {
        $household = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        if (null === $household) {
            throw new \RuntimeException('Fixtures must provide exactly one own-funds household.');
        }

        return $household;
    }

    private function createOperation(Account $account, int $amount, \DateTimeImmutable $date): void
    {
        $contractor = new Contractor('Test Contractor '.uniqid());
        $this->em()->persist($contractor);
        $this->em()->persist(new AccountOperation($account, $contractor, $date, $amount, $account->getCurrency()));
        $this->em()->flush();
    }
}
