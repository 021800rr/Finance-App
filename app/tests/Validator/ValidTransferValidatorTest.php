<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Validator\ValidTransfer;
use App\Validator\ValidTransferValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * The transfer form's checks of two real accounts are covered by the form and controller tests; these are the cases
 * those cannot reach.
 *
 * @extends ConstraintValidatorTestCase<ValidTransferValidator>
 */
final class ValidTransferValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ValidTransferValidator
    {
        return new ValidTransferValidator();
    }

    public function testLeavesAValueThatIsNotTheFormDataAlone(): void
    {
        $this->validator->validate('transfer', new ValidTransfer());

        $this->assertNoViolation();
    }

    public function testLeavesAccountsNotChosenYetToTheirOwnFields(): void
    {
        $notChosen = ['fromAccount' => null, 'toAccount' => null, 'amountTo' => null];

        $this->validator->validate($notChosen, new ValidTransfer());

        $this->assertNoViolation();
    }

    public function testWorksOnlyForItsOwnConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate([], new NotBlank());
    }
}
