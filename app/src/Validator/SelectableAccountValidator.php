<?php

declare(strict_types=1);

namespace App\Validator;

use App\Repository\AccountRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class SelectableAccountValidator extends ConstraintValidator
{
    public function __construct(private readonly AccountRepository $accountRepository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SelectableAccount) {
            throw new UnexpectedTypeException($constraint, SelectableAccount::class);
        }

        if (null === $value) {
            return;
        }

        $account = $this->accountRepository->findByIdOrNull($value);
        if (null !== $account && $account->isHidden()) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
