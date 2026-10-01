<?php

declare(strict_types=1);

namespace App\State\Pagination;

use ApiPlatform\State\Pagination\PaginatorInterface;

/**
 * Minimal paginator for state providers that already fetch a single page of results
 * from the database (offset/limit) instead of the whole collection.
 *
 * @template T of object
 *
 * @implements PaginatorInterface<T>
 * @implements \IteratorAggregate<mixed, T>
 */
final class OffsetPaginator implements PaginatorInterface, \IteratorAggregate
{
    /**
     * @param list<T> $items items of the current page only
     */
    public function __construct(
        private readonly array $items,
        private readonly int $page,
        private readonly int $itemsPerPage,
        private readonly int $totalItems,
    ) {
    }

    public function getCurrentPage(): float
    {
        return (float) $this->page;
    }

    public function getLastPage(): float
    {
        return (float) max(1, (int) ceil($this->totalItems / $this->itemsPerPage));
    }

    public function getItemsPerPage(): float
    {
        return (float) $this->itemsPerPage;
    }

    public function getTotalItems(): float
    {
        return (float) $this->totalItems;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return \Traversable<int, T>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }
}
