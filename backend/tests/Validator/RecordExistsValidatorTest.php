<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Entity\Account;
use App\Validator\RecordExists;
use App\Validator\RecordExistsValidator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Records found or not are covered by the API tests; these are the cases a request cannot reach.
 *
 * @extends ConstraintValidatorTestCase<RecordExistsValidator>
 */
final class RecordExistsValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): RecordExistsValidator
    {
        return new RecordExistsValidator($this->createStub(ManagerRegistry::class));
    }

    public function testLeavesAMissingIdToNotNull(): void
    {
        $this->validator->validate(null, new RecordExists(Account::class, message: 'account.not_found'));
        $this->validator->validate('', new RecordExists(Account::class, message: 'account.not_found'));

        $this->assertNoViolation();
    }

    public function testWorksOnlyForItsOwnConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(1, new NotBlank());
    }
}
