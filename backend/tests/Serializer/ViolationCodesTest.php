<?php

declare(strict_types=1);

namespace App\Tests\Serializer;

use App\Serializer\ViolationCodes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The items of "errors" a client gets for every refused value, whatever constraint raised it: "detail", where the
 * value is ("pointer" or "parameter"), a stable "code" and the "params".
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
     * API Platform emits one row per violation, in list order; anything else in the list is left out.
     */
    public function testLeavesOutRowsWithoutAMatchingViolation(): void
    {
        $rows = [0 => 'not a violation row', 1 => ['propertyPath' => 'amount'], 'extra' => ['propertyPath' => 'day']];
        $violation = $this->violation('This value should not be blank.', NotBlank::IS_BLANK_ERROR, new NotBlank());

        self::assertSame([], ViolationCodes::errors($rows, new ConstraintViolationList([$violation]), inQuery: false));
    }

    public function testAnErrorOfTheContentPointsToItsValueAndOneOfTheQueryNamesItsParameter(): void
    {
        $violation = $this->violation('This value should not be blank.', NotBlank::IS_BLANK_ERROR, new NotBlank());
        $list = new ConstraintViolationList([$violation]);

        self::assertSame(
            ['detail' => 'Message for curl.', 'pointer' => '#/field'],
            array_slice(ViolationCodes::errors([[]], $list, inQuery: false)[0] ?? [], 0, 2),
        );
        self::assertSame(
            ['detail' => 'Message for curl.', 'parameter' => 'field'],
            array_slice(ViolationCodes::errors([[]], $list, inQuery: true)[0] ?? [], 0, 2),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function propertyPaths(): iterable
    {
        yield 'a field' => ['amountFrom', '#/amountFrom'];
        yield 'nested and listed' => ['lines[0].amount', '#/lines/0/amount'];
        yield 'the whole content' => ['', '#'];
        // RFC 6901, sections 3 and 6: "~" and "/" escaped, then percent-encoded for the URI fragment.
        yield 'characters to escape' => ['a/b~c d', '#/a~1b~0c%20d'];
    }

    #[DataProvider('propertyPaths')]
    public function testAPropertyPathBecomesAJsonPointer(string $propertyPath, string $pointer): void
    {
        self::assertSame($pointer, ViolationCodes::pointer($propertyPath));
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
     * The item of "errors" for the violation.
     *
     * @return array<string, mixed>
     */
    private function row(ConstraintViolation $violation): array
    {
        $errors = ViolationCodes::errors(
            [['propertyPath' => 'field']],
            new ConstraintViolationList([$violation]),
            inQuery: false,
        );
        self::assertIsArray($errors[0] ?? null);

        return $errors[0];
    }
}
