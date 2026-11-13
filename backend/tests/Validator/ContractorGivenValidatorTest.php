<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\ApiResource\Input\AccountOperationInput;
use App\Repository\ContractorRepository;
use App\Validator\ContractorGiven;
use App\Validator\ContractorGivenValidator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * What the contractor fields of a request may say is covered by the API tests; these are the cases a request
 * cannot reach.
 *
 * @extends ConstraintValidatorTestCase<ContractorGivenValidator>
 */
final class ContractorGivenValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ContractorGivenValidator
    {
        return new ContractorGivenValidator(
            new RequestStack(),
            new ContractorRepository($this->createStub(ManagerRegistry::class)),
        );
    }

    public function testLeavesAnObjectWithoutContractorFieldsAlone(): void
    {
        $this->validator->validate(new \stdClass(), new ContractorGiven());

        $this->assertNoViolation();
    }

    /**
     * It reads which fields the request sent; without a JSON body there is nothing to check.
     */
    public function testLeavesAnInputWithoutARequestBodyAlone(): void
    {
        $this->validator->validate(new AccountOperationInput(), new ContractorGiven());

        $this->assertNoViolation();
    }

    public function testWorksOnlyForItsOwnConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new AccountOperationInput(), new NotBlank());
    }
}
