<?php

declare(strict_types=1);

namespace App\Constant;

final class Contractors
{
    /**
     * System contractor used as the counterparty for transfers between the
     * user's own accounts. Seeded by migrations/Version202609301900.php so
     * it exists on every environment, including production (fixtures are
     * dev/test-only and never run there).
     */
    public const string SELF_TRANSFER_NAME = 'Przelew własny';
}
