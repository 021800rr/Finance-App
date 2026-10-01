<?php

declare(strict_types=1);

namespace App\Validator;

use App\Constant\DatabaseLimits;

final class IdValidator
{
    private function __construct()
    {
    }

    public static function tryParse(mixed $value): ?int
    {
        $id = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => DatabaseLimits::POSTGRES_INT4_MAX]]
        );

        return false === $id ? null : $id;
    }
}
