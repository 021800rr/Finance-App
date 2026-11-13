<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Validator\NonZeroAmount;
use App\Validator\NonZeroAmountValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<NonZeroAmountValidator>
 */
final class NonZeroAmountValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): NonZeroAmountValidator
    {
        return new NonZeroAmountValidator();
    }

    public function testRefusesZero(): void
    {
        $this->validator->validate('0.00', new NonZeroAmount());

        $this->buildViolation('common.amount_cannot_be_zero')->assertRaised();
    }

    public function testAcceptsAnyOtherAmount(): void
    {
        $this->validator->validate('-0.01', new NonZeroAmount());

        $this->assertNoViolation();
    }

    /**
     * The constraints before it in the sequence report a value that is not text or not an amount.
     */
    public function testLeavesAValueThatIsNoAmountToTheOtherConstraints(): void
    {
        $this->validator->validate(0, new NonZeroAmount());
        $this->validator->validate('zero', new NonZeroAmount());

        $this->assertNoViolation();
    }

    public function testWorksOnlyForItsOwnConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('0.00', new NotBlank());
    }
}
