<?php

declare(strict_types=1);

namespace App\Validator;

use App\ApiResource\TransferResource;
use App\Repository\AccountRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class TransferAmountToValidator extends ConstraintValidator
{
    public function __construct(private readonly AccountRepository $accountRepository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TransferAmountTo) {
            throw new UnexpectedTypeException($constraint, TransferAmountTo::class);
        }

        $transfer = $this->context->getObject();
        if (!$transfer instanceof TransferResource || !$this->currenciesDiffer($transfer)) {
            return;
        }

        $this->context->getValidator()->inContext($this->context)->validate($value, new Sequentially([
            new Type('string'),
            new Range(
                notInRangeMessage: $constraint->outOfRangeMessage,
                invalidMessage: $constraint->invalidMessage,
                min: $constraint->min,
                max: $constraint->max,
            ),
        ]));
    }

    /**
     * False also when an account is missing: such a request is refused anyway (404).
     */
    private function currenciesDiffer(TransferResource $transfer): bool
    {
        if (null === $transfer->fromAccountId || null === $transfer->toAccountId) {
            return false;
        }

        $from = $this->accountRepository->findByIdOrNull($transfer->fromAccountId);
        $to = $this->accountRepository->findByIdOrNull($transfer->toAccountId);

        return
            null !== $from
            && null !== $to
            && $from->getCurrency()->getCode() !== $to->getCurrency()->getCode()
        ;
    }
}
