<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Account;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ValidTransferValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidTransfer) {
            throw new UnexpectedTypeException($constraint, ValidTransfer::class);
        }

        if (!is_array($value)) {
            return;
        }

        $fromAccount = $value['fromAccount'] ?? null;
        $toAccount = $value['toAccount'] ?? null;
        $amountTo = $value['amountTo'] ?? null;

        if (!$fromAccount instanceof Account || !$toAccount instanceof Account) {
            return;
        }

        if ($fromAccount->getId() === $toAccount->getId()) {
            $this->context->buildViolation($constraint->accountsMustDifferMessage)
                ->atPath('[toAccount]')
                ->addViolation();
        }

        if ($fromAccount->getCurrency()->getCode() !== $toAccount->getCurrency()->getCode() && null === $amountTo) {
            $this->context->buildViolation($constraint->amountToRequiredMessage)
                ->atPath('[amountTo]')
                ->addViolation();
        }
    }
}
