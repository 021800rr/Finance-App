<?php

declare(strict_types=1);

namespace App\Validator;

use App\Util\BusinessDateRange;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class BusinessDateValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof BusinessDate) {
            throw new UnexpectedTypeException($constraint, BusinessDate::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!$value instanceof \DateTimeInterface) {
            throw new UnexpectedValueException($value, \DateTimeInterface::class);
        }

        if (!BusinessDateRange::contains($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ min }}', BusinessDateRange::MIN_DATE)
                ->setParameter('{{ max }}', BusinessDateRange::MAX_DATE)
                ->addViolation();
        }
    }
}
