<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\Household;
use App\Repository\AccountRepository;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AccountRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private AccountRepository $accountRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var AccountRepository $accountRepository */
        $accountRepository = $this->em()->getRepository(Account::class);
        $this->accountRepository = $accountRepository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testFindByIdOrNullReturnsAccountForValidId(): void
    {
        $account = $this->createAccount();

        $result = $this->accountRepository->findByIdOrNull((string) $account->getId());

        self::assertNotNull($result);
        self::assertSame($account->getId(), $result->getId());
    }

    public function testFindByIdOrNullReturnsNullForNonNumericId(): void
    {
        $result = $this->accountRepository->findByIdOrNull('alamakota');

        self::assertNull($result);
    }

    public function testFindByIdOrNullReturnsNullForIdBelowMinimum(): void
    {
        $result = $this->accountRepository->findByIdOrNull('0');

        self::assertNull($result);
    }

    public function testFindByIdOrNullReturnsNullForIdExceedingMaximum(): void
    {
        $result = $this->accountRepository->findByIdOrNull('99999999999');

        self::assertNull($result);
    }

    public function testFindByIdOrNullReturnsNullForNonExistentValidId(): void
    {
        $result = $this->accountRepository->findByIdOrNull('999999999');

        self::assertNull($result);
    }

    private function createAccount(): Account
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);

        $currency = $this->findOrCreateCurrency('PLN');

        $accountType = new AccountType('Test Type ' . uniqid());
        $this->em()->persist($accountType);

        $household = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        if (null === $household) {
            throw new \RuntimeException('Fixtures must provide exactly one own-funds household.');
        }

        $account = new Account($bank, $currency, $accountType, 'ACC-' . uniqid(), $household);
        $this->em()->persist($account);
        $this->em()->flush();

        return $account;
    }

    private function findOrCreateCurrency(string $code): Currency
    {
        $repository = $this->em()->getRepository(Currency::class);
        $currency = $repository->findOneBy(['code' => $code]);
        if (null === $currency) {
            $currency = new Currency($code);
            $this->em()->persist($currency);
        }

        return $currency;
    }
}
