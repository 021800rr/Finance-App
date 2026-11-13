<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Repository\AccountRepository;
use App\Validator\SelectableAccount;
use App\Validator\SelectableAccountValidator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Hidden accounts refused is covered by the API tests; these are the cases a request cannot reach.
 *
 * @extends ConstraintValidatorTestCase<SelectableAccountValidator>
 */
final class SelectableAccountValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): SelectableAccountValidator
    {
        return new SelectableAccountValidator(new AccountRepository($this->createStub(ManagerRegistry::class)));
    }

    public function testLeavesAMissingAccountToNotNull(): void
    {
        $this->validator->validate(null, new SelectableAccount());

        $this->assertNoViolation();
    }

    public function testWorksOnlyForItsOwnConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(1, new NotBlank());
    }
}
