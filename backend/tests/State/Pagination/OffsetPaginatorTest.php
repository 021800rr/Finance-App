<?php

declare(strict_types=1);

namespace App\Tests\State\Pagination;

use App\State\Pagination\OffsetPaginator;
use PHPUnit\Framework\TestCase;

final class OffsetPaginatorTest extends TestCase
{
    /**
     * It holds one page only: its count is the items of that page, the total is the items of all pages.
     */
    public function testCountsTheItemsOfItsPageAndKnowsTheTotal(): void
    {
        $paginator = new OffsetPaginator([new \stdClass(), new \stdClass()], 3, 30, 62);

        self::assertCount(2, $paginator);
        self::assertSame(2, iterator_count($paginator));
        self::assertSame(62.0, $paginator->getTotalItems());
        self::assertSame(3.0, $paginator->getCurrentPage());
        self::assertSame(30.0, $paginator->getItemsPerPage());
        self::assertSame(3.0, $paginator->getLastPage());
    }

    public function testHasOnePageWhenThereIsNothing(): void
    {
        self::assertSame(1.0, new OffsetPaginator([], 1, 30, 0)->getLastPage());
    }
}
