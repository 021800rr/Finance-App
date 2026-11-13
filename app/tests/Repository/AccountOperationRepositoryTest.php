<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\AccountOperation;
use App\Repository\AccountOperationRepository;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class AccountOperationRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

    private AccountOperationRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var AccountOperationRepository $repository */
        $repository = self::getContainer()->get(AccountOperationRepository::class);
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

        $this->createOperation($accountA, 10000, new \DateTimeImmutable('-1 day'), description: 'A1');
        $this->createOperation($accountB, 20000, new \DateTimeImmutable(), description: 'B1');

        $result = $this->repository->findByAccountOrderedPaged($accountA, 20, 0);

        self::assertCount(1, $result);
        self::assertSame('A1', $result[0]->getDescription());
    }

    public function testFindByAccountOrderedPagedSortsByDateDescending(): void
    {
        $account = $this->createAccount();

        $this->createOperation($account, 1000, new \DateTimeImmutable('-3 days'), description: 'oldest');
        $this->createOperation($account, 2000, new \DateTimeImmutable('-1 day'), description: 'newest');

        $result = $this->repository->findByAccountOrderedPaged($account, 20, 0);

        self::assertSame('newest', $result[0]->getDescription());
        self::assertSame('oldest', $result[1]->getDescription());
    }

    public function testFindByAccountOrderedPagedRespectsLimitAndOffset(): void
    {
        $account = $this->createAccount();

        for ($i = 0; $i < 5; ++$i) {
            $this->createOperation($account, 1000, new \DateTimeImmutable("-{$i} days"), description: "op{$i}");
        }

        $result = $this->repository->findByAccountOrderedPaged($account, 2, 1);

        // Newest first, so skipping one leaves the operations from one and two days ago.
        $descriptions = array_map(static fn (AccountOperation $o): ?string => $o->getDescription(), $result);
        self::assertSame(['op1', 'op2'], $descriptions);
    }

    public function testCountByAccountReturnsCorrectCount(): void
    {
        $account = $this->createAccount();

        $this->createOperation($account, 1000, new \DateTimeImmutable(), description: 'a');
        $this->createOperation($account, 2000, new \DateTimeImmutable(), description: 'b');

        self::assertSame(2, $this->repository->countByAccount($account));
    }

    public function testSearchByQueryPagedFindsMatchByDescription(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 1000, new \DateTimeImmutable(), description: 'Unique searchable text');

        $result = $this->repository->searchByQueryPaged('searchable', 10, 0);

        self::assertCount(1, $result);
    }

    public function testSearchByQueryPagedFindsMatchByContractorName(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor('Écolier Unique '.uniqid());
        $this->createOperation($account, 1000, new \DateTimeImmutable(), $contractor, 'test');

        $result = $this->repository->searchByQueryPaged('Écolier', 10, 0);

        self::assertCount(1, $result);
    }

    public function testSearchByQueryPagedFindsMatchByExactAmount(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 15050, new \DateTimeImmutable(), description: 'irrelevant text');

        $result = $this->repository->searchByQueryPaged('150.5', 10, 0);

        self::assertCount(1, $result);
    }

    public function testSearchByQueryPagedFindsMatchByExactAmountWithoutDecimalPart(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 15000, new \DateTimeImmutable(), description: 'irrelevant text');

        $result = $this->repository->searchByQueryPaged('150', 10, 0);

        self::assertCount(1, $result);
    }

    public function testSearchByQueryPagedFindsMatchByExactNegativeAmount(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, -15050, new \DateTimeImmutable(), description: 'irrelevant text');

        $result = $this->repository->searchByQueryPaged('-150.50', 10, 0);

        self::assertCount(1, $result);
    }

    public function testSearchByQueryPagedIgnoresAmountSearchForNonNumericQuery(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 100, new \DateTimeImmutable(), description: 'completely unrelated text');

        $result = $this->repository->searchByQueryPaged('not-a-number', 10, 0);

        self::assertCount(0, $result);
    }

    public function testSearchByQueryPagedHandlesQueryWithLeadingAndTrailingWhitespace(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 15050, new \DateTimeImmutable(), description: 'irrelevant text');

        $result = $this->repository->searchByQueryPaged('  150.50  ', 10, 0);

        self::assertCount(1, $result);
    }

    public function testSearchByQueryPagedDoesNotMatchDifferentAmount(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 20000, new \DateTimeImmutable(), description: 'no match by text either');

        $result = $this->repository->searchByQueryPaged('150.50', 10, 0);

        self::assertCount(0, $result);
    }

    public function testSearchByQueryPagedFindsMatchByExactAmountWithCommaSeparator(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 15050, new \DateTimeImmutable(), description: 'irrelevant text');

        $result = $this->repository->searchByQueryPaged('150,5', 10, 0);

        self::assertCount(1, $result);
    }

    public function testSearchByQueryPagedReturnsEmptyForNonMatchingQuery(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 1000, new \DateTimeImmutable(), description: 'something');

        $result = $this->repository->searchByQueryPaged('nonexistent'.uniqid(), 10, 0);

        self::assertCount(0, $result);
    }

    public function testCountByQueryMatchesSearchByQueryPagedCount(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 1000, new \DateTimeImmutable(), description: 'countable match');

        self::assertSame(1, $this->repository->countByQuery('countable'));
    }

    public function testSearchByQueryPagedTreatsPercentAsLiteral(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $withPercent = $this->createOperation(
            $account,
            1000,
            new \DateTimeImmutable(),
            description: "Discount 100% {$marker}",
        );
        $plain = $this->createOperation(
            $account,
            1000,
            new \DateTimeImmutable(),
            description: "Discount 1000 {$marker}",
        );

        $ids = $this->idsOf($this->repository->searchByQueryPaged('100%', 100, 0));

        self::assertContains($withPercent->getId(), $ids);
        self::assertNotContains($plain->getId(), $ids);
    }

    public function testSearchByQueryPagedTreatsUnderscoreAsLiteral(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $withUnderscore = $this->createOperation(
            $account,
            1000,
            new \DateTimeImmutable(),
            description: "snake_case {$marker}",
        );
        $plain = $this->createOperation($account, 1000, new \DateTimeImmutable(), description: "snakeXcase {$marker}");

        $ids = $this->idsOf($this->repository->searchByQueryPaged("snake_case {$marker}", 100, 0));

        self::assertContains($withUnderscore->getId(), $ids);
        self::assertNotContains($plain->getId(), $ids);
    }

    public function testSearchByQueryPagedTreatsBackslashAsLiteral(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $withBackslash = $this->createOperation(
            $account,
            1000,
            new \DateTimeImmutable(),
            description: 'Path a\\b '.$marker,
        );
        $plain = $this->createOperation($account, 1000, new \DateTimeImmutable(), description: 'Path ab '.$marker);

        $ids = $this->idsOf($this->repository->searchByQueryPaged('a\\b '.$marker, 100, 0));

        self::assertContains($withBackslash->getId(), $ids);
        self::assertNotContains($plain->getId(), $ids);
    }

    public function testSearchByQueryPagedFindsDescriptionEndingWithBackslash(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $operation = $this->createOperation($account, 1000, new \DateTimeImmutable(), description: "Dir {$marker}\\");

        $ids = $this->idsOf($this->repository->searchByQueryPaged("{$marker}\\", 100, 0));

        self::assertContains($operation->getId(), $ids);
    }

    public function testCountByQueryTreatsPercentAsLiteral(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $this->createOperation($account, 1000, new \DateTimeImmutable(), description: "Rate 5% {$marker}");
        $this->createOperation($account, 1000, new \DateTimeImmutable(), description: "Rate 50 {$marker}");

        self::assertSame(1, $this->repository->countByQuery("5% {$marker}"));
    }

    public function testFindOtherSideReturnsNullWhenNoTransactionUuid(): void
    {
        $account = $this->createAccount();
        $operation = $this->createOperation($account, 1000, new \DateTimeImmutable(), description: 'no uuid');

        self::assertNull($this->repository->findOtherSide($operation));
    }

    public function testCountBeforeInAccountHistoryUsesDateThenIdAndExcludesOtherAccounts(): void
    {
        $account = $this->createAccount();
        $date = new \DateTimeImmutable('2026-10-22');
        $this->createOperation($account, date: $date);
        $target = $this->createOperation($account, date: $date);
        self::assertSame(0, $this->repository->countBeforeInAccountHistory($target));

        $this->createOperation($account, date: $date);
        $this->createOperation($account, date: $date->modify('+1 day'));
        $this->createOperation($account, date: $date->modify('-1 day'));
        $this->createOperation($this->createAccount(), date: $date->modify('+1 day'));

        self::assertSame(2, $this->repository->countBeforeInAccountHistory($target));
    }

    public function testFindOtherSideReturnsRelatedOperationWithSameUuid(): void
    {
        $accountA = $this->createAccount();
        $accountB = $this->createAccount();
        $uuid = Uuid::v7();

        $opOut = $this->createOperation(
            $accountA,
            -10000,
            new \DateTimeImmutable(),
            description: 'out',
            transactionUuid: $uuid,
        );
        $opIn = $this->createOperation(
            $accountB,
            10000,
            new \DateTimeImmutable(),
            description: 'in',
            transactionUuid: $uuid,
        );

        $result = $this->repository->findOtherSide($opOut);

        self::assertNotNull($result);
        self::assertSame($opIn->getId(), $result->getId());
    }

    #[DataProvider('outOfRangeQueries')]
    public function testOutOfRangeAmountQuerySearchesDescriptionsAndContractorNames(string $query): void
    {
        $account = $this->createAccount();
        $descriptionMatch = $this->createOperation(
            $account,
            100,
            new \DateTimeImmutable(),
            description: 'Reference '.$query,
        );
        $contractor = $this->createContractor('Contractor '.$query.' '.uniqid());
        $contractorMatch = $this->createOperation($account, 200, new \DateTimeImmutable(), $contractor, 'test');
        $this->createOperation($account, 99999999, new \DateTimeImmutable(), description: 'Unrelated maximum amount');
        $this->createOperation($account, -99999999, new \DateTimeImmutable(), description: 'Unrelated minimum amount');

        self::assertSame(2, $this->repository->countByQuery($query));
        self::assertEqualsCanonicalizing(
            [$descriptionMatch->getId(), $contractorMatch->getId()],
            $this->idsOf($this->repository->searchByQueryPaged($query, 10, 0)),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function outOfRangeQueries(): iterable
    {
        yield 'one million' => ['1000000'];
        yield 'negative one million' => ['-1000000'];
        yield 'just above maximum before rounding' => ['999999.991'];
        yield 'just below minimum before rounding' => ['-999999.991'];
        yield '13 digits' => [str_repeat('9', 13)];
        yield '17 digits' => [str_repeat('9', 17)];
        yield '33 digits' => [str_repeat('9', 33)];
        yield 'negative 33 digits' => ['-'.str_repeat('9', 33)];
    }

    /**
     * PHP takes 1e3 for a number, but no amount is written so: the query is text only, not 1000.00.
     */
    public function testExponentNotationQueryIsSearchedAsTextOnly(): void
    {
        $account = $this->createAccount();
        $textMatch = $this->createOperation($account, 100, new \DateTimeImmutable(), description: 'Invoice 1e3');
        $this->createOperation($account, 100000, new \DateTimeImmutable(), description: 'One thousand');

        self::assertSame(1, $this->repository->countByQuery('1e3'));
        self::assertSame([$textMatch->getId()], $this->idsOf($this->repository->searchByQueryPaged('1e3', 10, 0)));
    }

    #[DataProvider('boundaryAmountQueries')]
    public function testAmountQueryStillMatchesAtTheLimits(string $query, int $minorUnits): void
    {
        $operation = $this->createOperation(
            $this->createAccount(),
            $minorUnits,
            new \DateTimeImmutable(),
            description: 'Boundary amount',
        );

        self::assertSame(1, $this->repository->countByQuery($query));
        self::assertSame([$operation->getId()], $this->idsOf($this->repository->searchByQueryPaged($query, 10, 0)));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function boundaryAmountQueries(): iterable
    {
        yield 'maximum' => ['999999.99', 99999999];
        yield 'minimum' => ['-999999.99', -99999999];
        yield 'comma and whitespace' => [' 999999,99 ', 99999999];
        yield 'leading zeros' => [str_repeat('0', 33).'999999.99', 99999999];
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

    public function testExistsForContractorAndDateReturnsTrueForExactMatch(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor('Rent '.uniqid());
        $date = new \DateTimeImmutable('2026-03-15');

        $this->createOperation($account, 15050, $date, $contractor, 'Rent');

        $result = $this->repository->existsForContractorAndDate($account, $contractor, $date, 15050);

        self::assertTrue($result);
    }

    public function testExistsForContractorAndDateIgnoresTheTime(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor('Rent '.uniqid());
        $this->createOperation(
            $account,
            15050,
            new \DateTimeImmutable('2026-03-15 12:00:00'),
            $contractor,
            'test',
        );

        $result = $this->repository->existsForContractorAndDate(
            $account,
            $contractor,
            new \DateTimeImmutable('2026-03-15 23:59:59'),
            15050,
        );

        self::assertTrue($result);
    }

    public function testExistsForContractorAndDateReturnsFalseForDifferentAmount(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor('Rent '.uniqid());
        $date = new \DateTimeImmutable('2026-03-15');

        $this->createOperation($account, 15050, $date, $contractor, 'Rent');

        $result = $this->repository->existsForContractorAndDate($account, $contractor, $date, 20000);

        self::assertFalse($result);
    }

    public function testExistsForContractorAndDateReturnsFalseForDifferentDate(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor('Rent '.uniqid());

        $this->createOperation(
            $account,
            15050,
            new \DateTimeImmutable('2026-03-15'),
            $contractor,
            'Rent',
        );

        $result = $this->repository->existsForContractorAndDate(
            $account,
            $contractor,
            new \DateTimeImmutable('2026-03-16'),
            15050,
        );

        self::assertFalse($result);
    }

    public function testExistsForContractorAndDateReturnsFalseForDifferentContractor(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor('Rent '.uniqid());
        $otherContractor = $this->createContractor('Electricity '.uniqid());
        $date = new \DateTimeImmutable('2026-03-15');

        $this->createOperation($account, 15050, $date, $contractor, 'Rent');

        $result = $this->repository->existsForContractorAndDate($account, $otherContractor, $date, 15050);

        self::assertFalse($result);
    }

    public function testExistsForContractorAndDateReturnsFalseForDifferentAccount(): void
    {
        $account = $this->createAccount();
        $otherAccount = $this->createAccount();
        $contractor = $this->createContractor('Rent '.uniqid());
        $date = new \DateTimeImmutable('2026-03-15');

        $this->createOperation($account, 15050, $date, $contractor, 'Rent');

        $result = $this->repository->existsForContractorAndDate($otherAccount, $contractor, $date, 15050);

        self::assertFalse($result);
    }

    public function testExistsForContractorAndDateReturnsFalseWhenNoOperationsExist(): void
    {
        $account = $this->createAccount();
        $contractor = $this->createContractor('Rent '.uniqid());

        $result = $this->repository->existsForContractorAndDate($account, $contractor, new \DateTimeImmutable(), 15050);

        self::assertFalse($result);
    }

    public function testSumBalancesForAccountSplitsTotalAndUpToToday(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 100000, new \DateTimeImmutable('-5 days'), description: 'past');
        $this->createOperation($account, -20050, new \DateTimeImmutable('today'), description: 'today');
        $this->createOperation($account, 30000, new \DateTimeImmutable('+5 days'), description: 'future');

        $sums = $this->repository->sumBalancesForAccount($account, new \DateTimeImmutable('today'));

        self::assertSame(109950, $sums['total']);
        self::assertSame(79950, $sums['upToToday']);
    }

    public function testSumBalancesForAccountIsZeroWithoutOperations(): void
    {
        $sums = $this->repository->sumBalancesForAccount($this->createAccount(), new \DateTimeImmutable('today'));

        self::assertSame(['total' => 0, 'upToToday' => 0], $sums);
    }

    public function testSumBalancesForAccountIncludesTheWholeDayAndExcludesTomorrow(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 1000, new \DateTimeImmutable('2026-10-26 00:00:00'), description: 'midnight');
        $this->createOperation($account, 12345, new \DateTimeImmutable('2026-10-26 12:00:00'), description: 'noon');
        $this->createOperation(
            $account,
            -2345,
            new \DateTimeImmutable('2026-10-26 23:59:59'),
            description: 'end of day',
        );
        $this->createOperation($account, 5000, new \DateTimeImmutable('2026-10-27 00:00:00'), description: 'tomorrow');

        $sums = $this->repository->sumBalancesForAccount($account, new \DateTimeImmutable('2026-10-26'));

        self::assertSame(['total' => 16000, 'upToToday' => 11000], $sums);
    }

    public function testSaveStoresOnlyTheCalendarDateAndReloadsAtMidnight(): void
    {
        $operation = $this->createOperation(
            $this->createAccount(),
            12345,
            new \DateTimeImmutable('2026-10-26 12:34:56'),
            description: 'date without time',
        );
        $id = $operation->getId();

        $storedDate = $this->em()->getConnection()->fetchOne(
            'SELECT operation_date FROM account_operation WHERE id = ?',
            [$id],
        );
        self::assertSame('2026-10-26', $storedDate);

        $this->em()->clear();
        $reloaded = $this->repository->find($id);

        self::assertNotNull($reloaded);
        self::assertSame('2026-10-26 00:00:00', $reloaded->getOperationDate()->format('Y-m-d H:i:s'));
    }

    public function testSumBalancesForAccountIgnoresOtherAccounts(): void
    {
        $accountA = $this->createAccount();
        $accountB = $this->createAccount();
        $this->createOperation($accountA, 10000, new \DateTimeImmutable('today'), description: 'A');
        $this->createOperation($accountB, 999900, new \DateTimeImmutable('today'), description: 'B');

        $sums = $this->repository->sumBalancesForAccount($accountA, new \DateTimeImmutable('today'));

        self::assertSame(10000, $sums['total']);
    }
}
