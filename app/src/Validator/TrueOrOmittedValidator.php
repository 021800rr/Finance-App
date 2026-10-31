<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class TrueOrOmittedValidator extends ConstraintValidator
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TrueOrOmitted) {
            throw new UnexpectedTypeException($constraint, TrueOrOmitted::class);
        }

        $body = json_decode((string) $this->requestStack->getCurrentRequest()?->getContent(), true);
        $field = (string) $this->context->getPropertyName();

        if (is_array($body) && array_key_exists($field, $body) && true !== $body[$field]) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
