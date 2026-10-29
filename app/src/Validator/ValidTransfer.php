<?php

declare(strict_types=1);

namespace App\Validator;

use App\Exception\TransferException;
use Symfony\Component\Validator\Constraint;

/**
 * The messages are the same codes the API reports for these refusals (TransferException), so the web form and
 * the API name each problem alike.
 */
final class ValidTransfer extends Constraint
{
    public string $accountsMustDifferMessage = TransferException::ACCOUNTS_MUST_DIFFER;
    public string $amountToRequiredMessage = TransferException::AMOUNT_TO_REQUIRED;
}
