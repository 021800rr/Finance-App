<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * The amount is checked after rounding to minor units, because that is what gets stored: "0.001" is zero as well.
 */
final class NonZeroAmount extends Constraint
{
    public string $message = 'common.amount_cannot_be_zero';
}
