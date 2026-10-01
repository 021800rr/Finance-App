<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\ApiResource\AccountResource;
use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\Household;
use App\State\AccountProvider;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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

    public function testProvideReturnsNullForNonExistentId(): void
    {
        $result = $this->provider->provide(new Get(), ['id' => 999999999]);

        self::assertNull($result);
    }

    public function testProvideReturnsNullForNonNumericId(): void
    {
        $result = $this->provider->provide(new Get(), ['id' => 'not-a-number']);

        self::assertNull($result);
    }

    public function testProvideCollectionReturnsAllAccountsAsResources(): void
    {
        $first = $this->createAccount('ZZZ-'.uniqid());
        $second = $this->createAccount('AAA-'.uniqid());

        $result = $this->provider->provide(new GetCollection());

        self::assertIsArray($result);
        $ids = array_map(static fn (AccountResource $resource): ?int => $resource->id, $result);
        self::assertContains($first->getId(), $ids);
        self::assertContains($second->getId(), $ids);
        foreach ($result as $resource) {
            self::assertInstanceOf(AccountResource::class, $resource);
        }
    }

    public function testProvideCollectionOrdersByBankThenTypeThenCurrencyThenAccountNumber(): void
    {
        $bankA = new Bank('AAA Bank '.uniqid());
        $bankB = new Bank('ZZZ Bank '.uniqid());
        $this->em()->persist($bankA);
        $this->em()->persist($bankB);

        $currency = $this->ensurePlnCurrency();
        $accountType = new AccountType('Test Type '.uniqid());
        $this->em()->persist($accountType);

        $household = $this->ownFundsHousehold();

        $accountInBankB = new Account($bankB, $currency, $accountType, 'ACC-'.uniqid(), $household);
        $accountInBankA = new Account($bankA, $currency, $accountType, 'ACC-'.uniqid(), $household);
        $this->em()->persist($accountInBankB);
        $this->em()->persist($accountInBankA);
        $this->em()->flush();

        /** @var list<AccountResource> $result */
        $result = $this->provider->provide(new GetCollection());

        $positionOfA = array_search($accountInBankA->getId(), array_map(static fn (AccountResource $r): ?int => $r->id, $result), true);
        $positionOfB = array_search($accountInBankB->getId(), array_map(static fn (AccountResource $r): ?int => $r->id, $result), true);

        self::assertIsInt($positionOfA);
        self::assertIsInt($positionOfB);
        self::assertLessThan($positionOfB, $positionOfA, 'Account in "AAA Bank" should sort before account in "ZZZ Bank".');
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
}
