<?php

declare(strict_types=1);

namespace App\Tests\Database;

use App\Repository\LatestQuoteQuery;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The account history and the latest-quote queries must read their rows from an index already in the right order,
 * so their cost does not grow with a sort of the whole table. The test data is tiny, so the planner is told to avoid
 * a full scan, as it would on a large table.
 */
final class IndexTest extends KernelTestCase
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

    #[DataProvider('queries')]
    public function testTheQueryReadsAnIndexInsteadOfSorting(string $sql): void
    {
        $connection = $this->em()->getConnection();
        // Only for the transaction that wraps this test.
        $connection->executeStatement('SET LOCAL enable_seqscan = off');

        /** @var list<string> $lines */
        $lines = $connection->fetchFirstColumn('EXPLAIN (COSTS OFF) '.$sql);
        $plan = implode("\n", $lines);

        self::assertStringContainsString('Index Scan', $plan, $plan);
        self::assertStringNotContainsString('Sort', $plan, $plan);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function queries(): iterable
    {
        // The same order as AccountOperationRepository::findByAccountOrderedPaged().
        yield 'account history' => [
            'SELECT * FROM account_operation WHERE account_id = 1 ORDER BY operation_date DESC, id DESC LIMIT 20',
        ];
        yield 'latest rate per bank and currency' => [
            LatestQuoteQuery::build('currency_exchange_rate', ['bank_id', 'currency_code'], 'rate_date'),
        ];
        yield 'rate per bank, currency and day' => [
            LatestQuoteQuery::build('currency_exchange_rate', ['bank_id', 'currency_code'], 'rate_date', perDay: true),
        ];
        yield 'latest gold price per asset' => [LatestQuoteQuery::build('gold_price', ['gold_asset_id'], 'price_date')];
        yield 'gold price per asset and day' => [
            LatestQuoteQuery::build('gold_price', ['gold_asset_id'], 'price_date', perDay: true),
        ];
    }
}
