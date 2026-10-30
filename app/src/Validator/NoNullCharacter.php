<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

/**
 * Rejects text containing a NUL character. The database driver cuts a value at that character, so without this
 * check "ab\0cd" is stored as "ab" while the response still shows the full text.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class NoNullCharacter extends Compound
{
    /**
     * @param array<string, mixed> $options
     *
     * @return list<Assert\Regex>
     */
    protected function getConstraints(array $options): array
    {
        return [new Assert\Regex(pattern: '/\x00/', message: 'common.text_contains_null_character', match: false)];
    }
}
