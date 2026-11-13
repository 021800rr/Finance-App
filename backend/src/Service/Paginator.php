<?php

declare(strict_types=1);

namespace App\Service;

final readonly class Paginator
{
    public const int DEFAULT_PER_PAGE = 20;

    public function __construct(
        public int $page,
        public int $perPage,
        public int $totalPages,
        public int $offset,
    ) {
    }

    public static function create(int $requestedPage, int $totalItems, int $perPage): self
    {
        if ($perPage < 1) {
            throw new \InvalidArgumentException('perPage must be at least 1.');
        }

        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $page = max(1, $requestedPage);
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        return new self($page, $perPage, $totalPages, $offset);
    }
}
