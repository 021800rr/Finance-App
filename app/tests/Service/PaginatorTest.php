<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Paginator;
use PHPUnit\Framework\TestCase;

final class PaginatorTest extends TestCase
{
    public function testFirstPageWithFullResults(): void
    {
        $paginator = Paginator::create(1, 100, 20);

        self::assertSame(1, $paginator->page);
        self::assertSame(20, $paginator->perPage);
        self::assertSame(5, $paginator->totalPages);
        self::assertSame(0, $paginator->offset);
    }

    public function testMiddlePageCalculatesCorrectOffset(): void
    {
        $paginator = Paginator::create(3, 100, 20);

        self::assertSame(3, $paginator->page);
        self::assertSame(40, $paginator->offset);
    }

    public function testRequestedPageBeyondTotalPagesClampsToLastPage(): void
    {
        $paginator = Paginator::create(999, 43, 20);

        self::assertSame(3, $paginator->page);
        self::assertSame(3, $paginator->totalPages);
        self::assertSame(40, $paginator->offset);
    }

    public function testRequestedPageBelowOneClampsToFirstPage(): void
    {
        $paginator = Paginator::create(0, 100, 20);

        self::assertSame(1, $paginator->page);
        self::assertSame(0, $paginator->offset);
    }

    public function testNegativePageClampsToFirstPage(): void
    {
        $paginator = Paginator::create(-5, 100, 20);

        self::assertSame(1, $paginator->page);
    }

    public function testZeroTotalItemsStillReturnsOnePage(): void
    {
        $paginator = Paginator::create(1, 0, 20);

        self::assertSame(1, $paginator->totalPages);
        self::assertSame(1, $paginator->page);
        self::assertSame(0, $paginator->offset);
    }

    public function testPartialLastPageRoundsUpTotalPages(): void
    {
        $paginator = Paginator::create(1, 41, 20);

        self::assertSame(3, $paginator->totalPages);
    }

    public function testPerPageBelowOneThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('perPage must be at least 1.');

        Paginator::create(1, 100, 0);
    }
}
