<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Account;
use App\Repository\AccountRepository;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AccountRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

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
}
