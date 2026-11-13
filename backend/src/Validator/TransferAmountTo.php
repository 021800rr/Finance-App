<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * With one currency the credited amount is generated, so sending the field at all is refused. With two currencies
 * the amount is checked here, and a missing one is refused on saving (TransferException names both currencies).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class TransferAmountTo extends Constraint
{
    public string $notAllowedMessage = 'transfer.amount_to_not_allowed_for_same_currency';
    public string $outOfRangeMessage = 'transfer.amount_out_of_range';
    public string $invalidMessage = 'common.numeric_invalid';
    public float $min = 0.01;
    public float $max = 999999.99;
}
