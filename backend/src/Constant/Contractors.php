<?php

declare(strict_types=1);

namespace App\Constant;

final class Contractors
{
    /**
     * System contractor used as the counterparty for transfers between the
     * user's own accounts.
     */
    public const string SELF_TRANSFER_NAME = 'Przelew własny';
}
