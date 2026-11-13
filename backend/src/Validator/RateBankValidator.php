<?php

declare(strict_types=1);

namespace App\Validator;

use App\Repository\BankRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class RateBankValidator extends ConstraintValidator
{
    public function __construct(private readonly BankRepository $bankRepository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RateBank) {
            throw new UnexpectedTypeException($constraint, RateBank::class);
        }

        if (null === $value) {
            return;
        }

        $bank = $this->bankRepository->findByIdOrNull($value);
        if (null !== $bank && !$this->bankRepository->hasVisibleForeignCurrencyAccount($bank)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
