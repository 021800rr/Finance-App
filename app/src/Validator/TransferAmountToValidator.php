<?php

declare(strict_types=1);

namespace App\Validator;

use App\ApiResource\TransferResource;
use App\Repository\AccountRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class TransferAmountToValidator extends ConstraintValidator
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TransferAmountTo) {
            throw new UnexpectedTypeException($constraint, TransferAmountTo::class);
        }

        $transfer = $this->context->getObject();
        if (!$transfer instanceof TransferResource) {
            return;
        }

        $from = $this->accountRepository->findByIdOrNull($transfer->fromAccountId);
        $to = $this->accountRepository->findByIdOrNull($transfer->toAccountId);
        // A missing account is refused anyway (404).
        if (null === $from || null === $to) {
            return;
        }

        if ($from->getCurrency()->getCode() === $to->getCurrency()->getCode()) {
            if ($this->wasSent()) {
                $this->context->buildViolation($constraint->notAllowedMessage)->addViolation();
            }

            return;
        }

        if (null === $value || (is_string($value) && '' === trim($value))) {
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
     * Read from the request body, because the resource holds null both for a field left out and for a sent null.
     */
    private function wasSent(): bool
    {
        $body = json_decode((string) $this->requestStack->getCurrentRequest()?->getContent(), true);

        return is_array($body) && array_key_exists((string) $this->context->getPropertyName(), $body);
    }
}
