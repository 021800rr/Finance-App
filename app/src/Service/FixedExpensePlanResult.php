<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Outcome of planning the fixed expenses for one month.
 */
final readonly class FixedExpensePlanResult
{
    public function __construct(
        public \DateTimeImmutable $month,
        public int $createdCount,
    ) {
    }
}
