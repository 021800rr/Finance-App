<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class BusinessDate extends Constraint
{
    public string $message = 'common.date_out_of_range';

    /** For a date sent as text (API): not a real calendar date in the YYYY-MM-DD format. */
    public string $invalidMessage = 'common.date_invalid';
}
