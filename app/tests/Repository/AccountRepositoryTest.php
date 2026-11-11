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

    public function testAccountChoicesFollowSummaryOrderAndVisibleChoicesExcludeHiddenAccounts(): void
    {
        $second = $this->createAccount(accountNumber: 'SORT-B');
        $first = $this->createAccount(accountNumber: 'SORT-Z');
        $samePosition = $this->createAccount(accountNumber: 'SORT-A');
        $laterNumber = $this->createAccount(accountNumber: 'NO-SORT-2');
        $earlierNumber = $this->createAccount(accountNumber: 'NO-SORT-1');
        $hidden = $this->createAccount(accountNumber: 'SORT-HIDDEN', hidden: true);

        $connection = $this->em()->getConnection();
        foreach ([[$first, 0], [$second, 1], [$samePosition, 1], [$hidden, -1]] as [$account, $position]) {
            $connection->executeStatement(
                'UPDATE account SET sort_order = ? WHERE id = ?',
                [$position, $account->getId()],
            );
        }

        $accounts = [$hidden, $first, $samePosition, $second, $earlierNumber, $laterNumber];
        $expectedIds = array_map(static fn (Account $account): int => $account->getId(), $accounts);

        /** @var list<Account> $all */
        $all = $this->accountRepository->createOrderedForSelectQueryBuilder()->getQuery()->getResult();
        $allIds = array_map(static fn (Account $account): int => $account->getId(), $all);
        self::assertSame($expectedIds, array_values(array_intersect($allIds, $expectedIds)));

        $visible = $this->accountRepository->findVisibleOrderedForSelect();
        $visibleIds = array_map(static fn (Account $account): int => $account->getId(), $visible);
        self::assertSame(array_slice($expectedIds, 1), array_values(array_intersect($visibleIds, $expectedIds)));
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
