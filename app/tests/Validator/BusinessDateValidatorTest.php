<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Util\BusinessDateRange;
use App\Validator\BusinessDate;
use App\Validator\BusinessDateValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<BusinessDateValidator>
 */
final class BusinessDateValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): BusinessDateValidator
    {
        return new BusinessDateValidator();
    }

    public function testAcceptsADateInTheRangeAsTextOrAsADate(): void
    {
        $this->validator->validate('2026-11-12', new BusinessDate());
        $this->validator->validate(new \DateTimeImmutable('2026-11-12'), new BusinessDate());

        $this->assertNoViolation();
    }

    public function testLeavesAMissingDateToNotBlank(): void
    {
        $this->validator->validate(null, new BusinessDate());
        $this->validator->validate('', new BusinessDate());

        $this->assertNoViolation();
    }

    public function testRefusesTextThatIsNoCalendarDate(): void
    {
        $this->validator->validate('2026-02-31', new BusinessDate());

        $this->buildViolation('common.date_invalid')->assertRaised();
    }

    public function testRefusesADateOutsideTheRangeAndNamesTheRange(): void
    {
        $this->validator->validate('2099-01-01', new BusinessDate());

        $this->buildViolation('common.date_out_of_range')
            ->setParameter('{{ min }}', BusinessDateRange::MIN_DATE)
            ->setParameter('{{ max }}', BusinessDateRange::MAX_DATE)
            ->assertRaised();
    }

    public function testRefusesAValueThatIsNeitherTextNorADate(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(20261112, new BusinessDate());
    }

    public function testWorksOnlyForItsOwnConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('2026-11-12', new NotBlank());
    }
}
