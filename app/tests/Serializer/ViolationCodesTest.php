<?php

declare(strict_types=1);

namespace App\Tests\Serializer;

use App\Serializer\ViolationCodes;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

final class ViolationCodesTest extends TestCase
{
    /**
     * Compared with another field (propertyPath), "{{ compared_value }}" is that field's submitted value.
     */
    public function testComparedValueIsNeverExposed(): void
    {
        $constraint = new GreaterThan(propertyPath: 'amountFrom', message: 'transfer.amount_to_too_low');
        $violation = new ConstraintViolation(
            'Too low.',
            'transfer.amount_to_too_low',
            [
                '{{ value }}' => '"5"',
                '{{ compared_value }}' => '"secret 123"',
                '{{ compared_value_path }}' => 'amountFrom',
            ],
            null,
            'amountTo',
            '5',
            null,
            GreaterThan::TOO_LOW_ERROR,
            $constraint,
        );

        $rows = ViolationCodes::apply([['propertyPath' => 'amountTo']], new ConstraintViolationList([$violation]));

        self::assertIsArray($rows[0] ?? null);
        self::assertSame('transfer.amount_to_too_low', $rows[0]['code'] ?? null);
        self::assertEquals(new \ArrayObject(), $rows[0]['params'] ?? null);
    }
}
