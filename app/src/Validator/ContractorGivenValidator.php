<?php

declare(strict_types=1);

namespace App\Validator;

use App\ApiResource\AccountOperationResource;
use App\ApiResource\FixedExpenseResource;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ContractorGivenValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ContractorGiven) {
            throw new UnexpectedTypeException($constraint, ContractorGiven::class);
        }

        $resource = $this->context->getObject();
        if (!$resource instanceof AccountOperationResource && !$resource instanceof FixedExpenseResource) {
            return;
        }

        if (is_string($value) && '' !== trim($value)) {
            return;
        }

        // On PATCH the id is always filled from the stored record (see ContractorResolver::resolveForUpdate()),
        // so a name sent as blank is the only sign of a client trying to clear the contractor.
        $blankNameOnUpdate = null !== $resource->id && null !== $value;

        if (null === $resource->contractorId || $blankNameOnUpdate) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
