<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

final class ValidTransfer extends Constraint
{
    public string $accountsMustDifferMessage = 'transfer.accounts_must_differ';
    public string $amountToRequiredMessage = 'transfer.amount_to_required_for_currency_exchange';
}
