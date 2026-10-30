<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Checks the credited amount of a transfer only when the two accounts use different currencies: with one currency
 * the field plays no role, whatever it contains.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class TransferAmountTo extends Constraint
{
    public string $outOfRangeMessage = 'transfer.amount_out_of_range';
    public string $invalidMessage = 'common.numeric_invalid';
    public float $min = 0.01;
    public float $max = 999999.99;
}
