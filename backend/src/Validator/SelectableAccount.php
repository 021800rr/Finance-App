<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * An account id given through the API must point to an account the web forms offer: hidden accounts are not
 * selectable. A missing or unknown id is left to the other constraints.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class SelectableAccount extends Constraint
{
    public string $message = 'account.hidden_not_selectable';
}
