<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * For a PATCH field whose only allowed value is true. The value is read from the request body, because on PATCH
 * the resource already holds the stored value, so a field that was left out cannot be told apart from a sent one.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class TrueOrOmitted extends Constraint
{
    public string $message = 'account.is_main_must_be_true';
}
