<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\Household;
use App\Repository\AccountOperationRepository;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class AccountOperationRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private AccountOperationRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var AccountOperationRepository $repository */
        $repository = AccountOperationRepositoryTest::getContainer()->get(AccountOperationRepository::class);
        $this->repository = $repository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testFindByAccountOrderedPagedReturnsOnlyOperationsForGivenAccount(): void
    {
        $accountA = $this->createAccount();
        $accountB = $this->createAccount();

        $this->createOperation($accountA, 10000, new DateTimeImmutable('-1 day'), 'A1');
        $this->createOperation($accountB, 20000, new DateTimeImmutable(), 'B1');

        $result = $this->repository->findByAccountOrderedPaged($accountA, 20, 0);

        self::assertCount(1, $result);
        self::assertSame('A1', $result[0]->getDescription());
    }

    public function testFindByAccountOrderedPagedSortsByDateDescending(): void
    {
        $account = $this->createAccount();

        $this->createOperation($account, 1000, new DateTimeImmutable('-3 days'), 'oldest');
        $this->createOperation($account, 2000, new DateTimeImmutable('-1 day'), 'newest');

        $result = $this->repository->findByAccountOrderedPaged($account, 20, 0);

        self::assertSame('newest', $result[0]->getDescription());
        self::assertSame('oldest', $result[1]->getDescription());
    }

    public function testFindByAccountOrderedPagedRespectsLimitAndOffset(): void
    {
        $account = $this->createAccount();

        for ($i = 0; $i < 5; ++$i) {
            $this->createOperation($account, 1000, new DateTimeImmutable("-{$i} days"), "op{$i}");
        }

        $result = $this->repository->findByAccountOrderedPaged($account, 2, 1);

        self::assertCount(2, $result);
    }

    public function testCountByAccountReturnsCorrectCount(): void
    {
        $account = $this->createAccount();

        $this->createOperation($account, 1000, new DateTimeImmutable(), 'a');
        $this->createOperation($account, 2000, new DateTimeImmutable(), 'b');

        self::assertSame(2, $this->repository->countByAccount($account));
    }

    public function testSearchByQueryPagedFindsMatchByDescription(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 1000, new DateTimeImmutable(), 'Unique searchable text');

        $result = $this->repository->searchByQueryPaged('searchable', 10, 0);

        self::assertCount(1, $result);
    }

    public function testSearchByQueryPagedFindsMatchByContractorName(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractorWithName('Écolier Unique');
        $this->createOperationWithContractor($account, 1000, new DateTimeImmutable(), $contractor);

        $result = $this->repository->searchByQueryPaged('Écolier', 10, 0);

        self::assertCount(1, $result);
    }

    public function testSearchByQueryPagedFindsMatchByExactAmount(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 15050, new DateTimeImmutable(), 'irrelevant text');

        $result = $this->repository->searchByQueryPaged('150.5', 10, 0);

        self::assertCount(1, $result);
    }

    public function testSearchByQueryPagedFindsMatchByExactAmountWithoutDecimalPart(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 15000, new DateTimeImmutable(), 'irrelevant text');

        $result = $this->repository->searchByQueryPaged('150', 10, 0);

        self::assertCount(1, $result);
    }

    public function testSearchByQueryPagedFindsMatchByExactNegativeAmount(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, -15050, new DateTimeImmutable(), 'irrelevant text');

        $result = $this->repository->searchByQueryPaged('-150.50', 10, 0);

        self::assertCount(1, $result);
    }

    public function testSearchByQueryPagedIgnoresAmountSearchForNonNumericQuery(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 100, new DateTimeImmutable(), 'completely unrelated text');

        $result = $this->repository->searchByQueryPaged('not-a-number', 10, 0);

        self::assertCount(0, $result);
    }

    public function testSearchByQueryPagedHandlesQueryWithLeadingAndTrailingWhitespace(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 15050, new DateTimeImmutable(), 'irrelevant text');

        $result = $this->repository->searchByQueryPaged('  150.50  ', 10, 0);

        self::assertCount(1, $result);
    }

    public function testSearchByQueryPagedDoesNotMatchDifferentAmount(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 20000, new DateTimeImmutable(), 'no match by text either');

        $result = $this->repository->searchByQueryPaged('150.50', 10, 0);

        self::assertCount(0, $result);
    }

    public function testSearchByQueryPagedFindsMatchByExactAmountWithCommaSeparator(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 15050, new DateTimeImmutable(), 'irrelevant text');

        $result = $this->repository->searchByQueryPaged('150,5', 10, 0);

        self::assertCount(1, $result);
    }

    public function testSearchByQueryPagedReturnsEmptyForNonMatchingQuery(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 1000, new DateTimeImmutable(), 'something');

        $result = $this->repository->searchByQueryPaged('nonexistent' . uniqid(), 10, 0);

        self::assertCount(0, $result);
    }

    public function testCountByQueryMatchesSearchByQueryPagedCount(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 1000, new DateTimeImmutable(), 'countable match');

        self::assertSame(1, $this->repository->countByQuery('countable'));
    }

    public function testSearchByQueryPagedTreatsPercentAsLiteral(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $withPercent = $this->createOperation($account, 1000, new DateTimeImmutable(), "Discount 100% {$marker}");
        $plain = $this->createOperation($account, 1000, new DateTimeImmutable(), "Discount 1000 {$marker}");

        $ids = $this->idsOf($this->repository->searchByQueryPaged('100%', 100, 0));

        self::assertContains($withPercent->getId(), $ids);
        self::assertNotContains($plain->getId(), $ids);
    }

    public function testSearchByQueryPagedTreatsUnderscoreAsLiteral(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $withUnderscore = $this->createOperation($account, 1000, new DateTimeImmutable(), "snake_case {$marker}");
        $plain = $this->createOperation($account, 1000, new DateTimeImmutable(), "snakeXcase {$marker}");

        $ids = $this->idsOf($this->repository->searchByQueryPaged("snake_case {$marker}", 100, 0));

        self::assertContains($withUnderscore->getId(), $ids);
        self::assertNotContains($plain->getId(), $ids);
    }

    public function testSearchByQueryPagedTreatsBackslashAsLiteral(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $withBackslash = $this->createOperation($account, 1000, new DateTimeImmutable(), 'Path a\\b '.$marker);
        $plain = $this->createOperation($account, 1000, new DateTimeImmutable(), 'Path ab '.$marker);

        $ids = $this->idsOf($this->repository->searchByQueryPaged('a\\b '.$marker, 100, 0));

        self::assertContains($withBackslash->getId(), $ids);
        self::assertNotContains($plain->getId(), $ids);
    }

    public function testSearchByQueryPagedFindsDescriptionEndingWithBackslash(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $operation = $this->createOperation($account, 1000, new DateTimeImmutable(), "Dir {$marker}\\");

        $ids = $this->idsOf($this->repository->searchByQueryPaged("{$marker}\\", 100, 0));

        self::assertContains($operation->getId(), $ids);
    }

    public function testCountByQueryTreatsPercentAsLiteral(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $this->createOperation($account, 1000, new DateTimeImmutable(), "Rate 5% {$marker}");
        $this->createOperation($account, 1000, new DateTimeImmutable(), "Rate 50 {$marker}");

        self::assertSame(1, $this->repository->countByQuery("5% {$marker}"));
    }

    public function testFindOtherSideReturnsNullWhenNoTransactionUuid(): void
    {
        $account = $this->createAccount();
        $operation = $this->createOperation($account, 1000, new DateTimeImmutable(), 'no uuid');

        self::assertNull($this->repository->findOtherSide($operation));
    }

    public function testFindOtherSideReturnsRelatedOperationWithSameUuid(): void
    {
        $accountA = $this->createAccount();
        $accountB = $this->createAccount();
        $uuid = Uuid::v7();

        $opOut = $this->createOperation($accountA, -10000, new DateTimeImmutable(), 'out', $uuid);
        $opIn = $this->createOperation($accountB, 10000, new DateTimeImmutable(), 'in', $uuid);

        $result = $this->repository->findOtherSide($opOut);

        self::assertNotNull($result);
        self::assertSame($opIn->getId(), $result->getId());
    }

    private function createAccount(): Account
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);

        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->find('PLN');
        if (null === $currency) {
            $currency = new Currency('PLN');
            $this->em()->persist($currency);
        }

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

    /**
     * @param AccountOperation[] $operations
     *
     * @return list<int>
     */
    private function idsOf(array $operations): array
    {
        return array_values(array_map(static fn (AccountOperation $o): int => $o->getId(), $operations));
    }

    private function createContractorWithName(string $name): Contractor
    {
        $contractor = new Contractor($name . ' ' . uniqid());
        $this->em()->persist($contractor);
        $this->em()->flush();

        return $contractor;
    }

    private function createOperation(
        Account $account,
        int $amount,
        DateTimeImmutable $date,
        string $description,
        ?Uuid $transactionUuid = null,
    ): AccountOperation {
        $contractor = new Contractor('Test Contractor ' . uniqid());
        $this->em()->persist($contractor);

        return $this->createOperationWithContractor($account, $amount, $date, $contractor, $description, $transactionUuid);
    }

    private function createOperationWithContractor(
        Account $account,
        int $amount,
        DateTimeImmutable $date,
        Contractor $contractor,
        string $description = 'test',
        ?Uuid $transactionUuid = null,
    ): AccountOperation {
        $operation = new AccountOperation(
            $account,
            $contractor,
            $date,
            $amount,
            $account->getCurrency(),
            description: $description,
            transactionUuid: $transactionUuid,
        );
        $this->em()->persist($operation);
        $this->em()->flush();

        return $operation;
    }

    public function testExistsForContractorAndDateReturnsTrueForExactMatch(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractorWithName('Rent');
        $date = new DateTimeImmutable('2026-03-15');

        $this->createOperationWithContractor($account, 15050, $date, $contractor, 'Rent');

        $result = $this->repository->existsForContractorAndDate($account, $contractor, $date, 15050);

        self::assertTrue($result);
    }

    public function testExistsForContractorAndDateReturnsFalseForDifferentAmount(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractorWithName('Rent');
        $date = new DateTimeImmutable('2026-03-15');

        $this->createOperationWithContractor($account, 15050, $date, $contractor, 'Rent');

        $result = $this->repository->existsForContractorAndDate($account, $contractor, $date, 20000);

        self::assertFalse($result);
    }

    public function testExistsForContractorAndDateReturnsFalseForDifferentDate(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractorWithName('Rent');

        $this->createOperationWithContractor($account, 15050, new DateTimeImmutable('2026-03-15'), $contractor, 'Rent');

        $result = $this->repository->existsForContractorAndDate($account, $contractor, new DateTimeImmutable('2026-03-16'), 15050);

        self::assertFalse($result);
    }

    public function testExistsForContractorAndDateReturnsFalseForDifferentContractor(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractorWithName('Rent');
        $otherContractor = $this->createContractorWithName('Electricity');
        $date = new DateTimeImmutable('2026-03-15');

        $this->createOperationWithContractor($account, 15050, $date, $contractor, 'Rent');

        $result = $this->repository->existsForContractorAndDate($account, $otherContractor, $date, 15050);

        self::assertFalse($result);
    }

    public function testExistsForContractorAndDateReturnsFalseForDifferentAccount(): void
    {
        $account = $this->createAccount();
        $otherAccount = $this->createAccount();
        $contractor = $this->createContractorWithName('Rent');
        $date = new DateTimeImmutable('2026-03-15');

        $this->createOperationWithContractor($account, 15050, $date, $contractor, 'Rent');

        $result = $this->repository->existsForContractorAndDate($otherAccount, $contractor, $date, 15050);

        self::assertFalse($result);
    }

    public function testExistsForContractorAndDateReturnsFalseWhenNoOperationsExist(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractorWithName('Rent');

        $result = $this->repository->existsForContractorAndDate($account, $contractor, new DateTimeImmutable(), 15050);

        self::assertFalse($result);
    }

    public function testSumBalancesForAccountSplitsTotalAndUpToToday(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 100000, new DateTimeImmutable('-5 days'), 'past');
        $this->createOperation($account, -20050, new DateTimeImmutable('today'), 'today');
        $this->createOperation($account, 30000, new DateTimeImmutable('+5 days'), 'future');

        $sums = $this->repository->sumBalancesForAccount($account, new DateTimeImmutable('today'));

        self::assertSame(109950, $sums['total']);
        self::assertSame(79950, $sums['upToToday']);
    }

    public function testSumBalancesForAccountIsZeroWithoutOperations(): void
    {
        $sums = $this->repository->sumBalancesForAccount($this->createAccount(), new DateTimeImmutable('today'));

        self::assertSame(['total' => 0, 'upToToday' => 0], $sums);
    }

    public function testSumBalancesForAccountIgnoresOtherAccounts(): void
    {
        $accountA = $this->createAccount();
        $accountB = $this->createAccount();
        $this->createOperation($accountA, 10000, new DateTimeImmutable('today'), 'A');
        $this->createOperation($accountB, 999900, new DateTimeImmutable('today'), 'B');

        $sums = $this->repository->sumBalancesForAccount($accountA, new DateTimeImmutable('today'));

        self::assertSame(10000, $sums['total']);
    }
}
