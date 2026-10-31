<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * A bank id given through the API must point to a bank the rate form offers: one with at least one visible
 * foreign-currency (non-PLN) account. A missing or unknown id is left to the other constraints.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class RateBank extends Constraint
{
    public string $message = 'currency_exchange_rate.bank_without_foreign_account';
}
