<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Repository\BankRepository;
use App\Validator\RateBank;
use App\Validator\RateBankValidator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Which banks take rates is covered by the exchange rate API and form tests; these are the cases a request cannot
 * reach.
 *
 * @extends ConstraintValidatorTestCase<RateBankValidator>
 */
final class RateBankValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): RateBankValidator
    {
        return new RateBankValidator(new BankRepository($this->createStub(ManagerRegistry::class)));
    }

    public function testLeavesAMissingBankToNotNull(): void
    {
        $this->validator->validate(null, new RateBank());

        $this->assertNoViolation();
    }

    public function testWorksOnlyForItsOwnConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(1, new NotBlank());
    }
}
