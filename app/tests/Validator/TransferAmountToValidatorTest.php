<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Repository\AccountRepository;
use App\Validator\TransferAmountTo;
use App\Validator\TransferAmountToValidator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * The amount credited between two real accounts is covered by the transfer API tests; these are the cases a request
 * cannot reach.
 *
 * @extends ConstraintValidatorTestCase<TransferAmountToValidator>
 */
final class TransferAmountToValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): TransferAmountToValidator
    {
        return new TransferAmountToValidator(
            new AccountRepository($this->createStub(ManagerRegistry::class)),
            new RequestStack(),
        );
    }

    public function testLeavesAFieldOutsideATransferAlone(): void
    {
        $this->setObject(new \stdClass());

        $this->validator->validate('10.00', new TransferAmountTo());

        $this->assertNoViolation();
    }

    public function testWorksOnlyForItsOwnConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('10.00', new NotBlank());
    }
}
