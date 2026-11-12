<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Outcome of planning the fixed expenses for one month.
 */
final readonly class FixedExpensePlanResult
{
    public function __construct(
        /** The main account the operations were created on, as locked during the planning. */
        public int $accountId,
        public \DateTimeImmutable $month,
        public int $createdCount,
    ) {
    }
}
