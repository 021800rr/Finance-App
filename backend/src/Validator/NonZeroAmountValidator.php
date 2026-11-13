<?php

declare(strict_types=1);

namespace App\Validator;

use App\Constant\Currencies;
use App\ValueObject\Money;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class NonZeroAmountValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NonZeroAmount) {
            throw new UnexpectedTypeException($constraint, NonZeroAmount::class);
        }

        if (!is_string($value)) {
            return;
        }

        try {
            // Every currency has the same scale, so the code does not change the result.
            $isZero = Money::fromDecimalString($value, Currencies::SETTLEMENT_CURRENCY_CODE)->isZero();
        } catch (\InvalidArgumentException) {
            return;
        }

        if ($isZero) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
