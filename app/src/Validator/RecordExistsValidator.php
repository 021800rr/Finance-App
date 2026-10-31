<?php

declare(strict_types=1);

namespace App\Validator;

use App\Util\IdParser;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class RecordExistsValidator extends ConstraintValidator
{
    public function __construct(private readonly ManagerRegistry $registry)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RecordExists) {
            throw new UnexpectedTypeException($constraint, RecordExists::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        // Currencies are identified by their code, with the same trim() as the processor uses to look them up.
        $id = is_string($value) ? trim($value) : IdParser::tryParse($value);

        if (null === $id || null === $this->registry->getRepository($constraint->entity)->find($id)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
