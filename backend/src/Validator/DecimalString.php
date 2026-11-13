<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

/**
 * Accepts only the format Money and Quantity can parse: digits, an optional minus sign and an optional fraction after
 * a dot. Range alone lets through anything PHP calls numeric ("+5", ".5", "5.", "1e2"), which then failed without
 * naming the field.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class DecimalString extends Compound
{
    /**
     * @param array<string, mixed> $options
     *
     * @return list<Assert\Regex>
     */
    protected function getConstraints(array $options): array
    {
        return [new Assert\Regex(pattern: '/^-?\d+(\.\d+)?$/', message: 'common.numeric_invalid', normalizer: 'trim')];
    }
}
