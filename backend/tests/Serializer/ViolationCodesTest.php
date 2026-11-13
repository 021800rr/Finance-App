<?php

declare(strict_types=1);

namespace App\Tests\Serializer;

use App\Serializer\ViolationCodes;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The stable "code" and the "params" a client gets for every field error, whatever constraint raised it.
 */
final class ViolationCodesTest extends TestCase
{
    public function testAViolationWithOurOwnTranslationKeyHasThatKeyAsItsCode(): void
    {
        $violation = $this->violation('transfer.to_account_required', NotBlank::IS_BLANK_ERROR, new NotBlank());

        self::assertSame('transfer.to_account_required', $this->row($violation)['code'] ?? null);
    }

    public function testASymfonyConstraintGivesTheNameOfItsError(): void
    {
        $violation = $this->violation('This value should not be blank.', NotBlank::IS_BLANK_ERROR, new NotBlank());

        self::assertSame('validation.is_blank', $this->row($violation)['code'] ?? null);
    }

    /**
     * The serializer reports a value of the wrong type without a constraint object.
     */
    public function testAViolationWithoutItsConstraintIsReadAsAWrongType(): void
    {
        $violation = $this->violation('This value should be of type int.', Type::INVALID_TYPE_ERROR, null);

        self::assertSame('validation.invalid_type', $this->row($violation)['code'] ?? null);
    }

    public function testACodeTheConstraintDoesNotKnowGetsTheGeneralCode(): void
    {
        $violation = $this->violation('This value is not valid.', 'not-a-symfony-error-code', new NotBlank());

        self::assertSame('validation.invalid', $this->row($violation)['code'] ?? null);
    }

    public function testAViolationWithoutACodeGetsTheGeneralCode(): void
    {
        $violation = $this->violation('This value is not valid.', null, null);

        self::assertSame('validation.invalid', $this->row($violation)['code'] ?? null);
    }

    public function testExposesOnlyThePublicParametersAndAsText(): void
    {
        $violation = $this->violation(
            'This value is too long.',
            Length::TOO_LONG_ERROR,
            new Length(max: 255),
            ['{{ limit }}' => 255, '{{ value }}' => '"the submitted text"', '{{ max }}' => ['not', 'scalar']],
        );

        $row = $this->row($violation);

        self::assertSame('validation.too_long', $row['code'] ?? null);
        self::assertSame(['limit' => '255'], $row['params'] ?? null);
    }

    /**
     * Compared with another field (propertyPath), "{{ compared_value }}" is that field's submitted value.
     */
    public function testComparedValueIsNeverExposed(): void
    {
        $violation = $this->violation(
            'transfer.amount_to_too_low',
            GreaterThan::TOO_LOW_ERROR,
            new GreaterThan(propertyPath: 'amountFrom', message: 'transfer.amount_to_too_low'),
            [
                '{{ value }}' => '"5"',
                '{{ compared_value }}' => '"secret 123"',
                '{{ compared_value_path }}' => 'amountFrom',
            ],
        );

        $row = $this->row($violation);

        self::assertSame('transfer.amount_to_too_low', $row['code'] ?? null);
        self::assertEquals(new \ArrayObject(), $row['params'] ?? null);
    }

    /**
     * API Platform emits one row per violation, in list order; anything else in the list is not ours to change.
     */
    public function testLeavesRowsWithoutAMatchingViolationAsTheyAre(): void
    {
        $rows = [
            0 => 'not a violation row',
            1 => ['propertyPath' => 'amount', 'code' => 'kept'],
            'extra' => ['propertyPath' => 'day', 'code' => 'kept'],
        ];
        $violation = $this->violation('This value should not be blank.', NotBlank::IS_BLANK_ERROR, new NotBlank());

        self::assertSame($rows, ViolationCodes::apply($rows, new ConstraintViolationList([$violation])));
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function violation(
        string $messageTemplate,
        ?string $code,
        ?Constraint $constraint,
        array $parameters = [],
    ): ConstraintViolation {
        return new ConstraintViolation(
            'Message for curl.',
            $messageTemplate,
            $parameters,
            null,
            'field',
            'submitted',
            null,
            $code,
            $constraint,
        );
    }

    /**
     * The row API Platform emits for the violation, as ViolationCodes leaves it.
     *
     * @return array<mixed>
     */
    private function row(ConstraintViolation $violation): array
    {
        $rows = ViolationCodes::apply([['propertyPath' => 'field']], new ConstraintViolationList([$violation]));
        self::assertIsArray($rows[0] ?? null);

        return $rows[0];
    }
}
