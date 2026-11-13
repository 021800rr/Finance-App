<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Validator\TrueOrOmitted;
use App\Validator\TrueOrOmittedValidator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * What isMain may be in a request is covered by the account API tests; these are the cases a request cannot reach.
 *
 * @extends ConstraintValidatorTestCase<TrueOrOmittedValidator>
 */
final class TrueOrOmittedValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): TrueOrOmittedValidator
    {
        return new TrueOrOmittedValidator(new RequestStack());
    }

    public function testHasNothingToCheckWithoutARequest(): void
    {
        $this->validator->validate(false, new TrueOrOmitted());

        $this->assertNoViolation();
    }

    public function testWorksOnlyForItsOwnConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(true, new NotBlank());
    }
}
