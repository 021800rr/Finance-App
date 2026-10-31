<?php

declare(strict_types=1);

namespace App\Serializer;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\DenormalizationViolationFactoryInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Keeps the expected PHP type of a wrong-type violation as a parameter, so ViolationCodes can expose it in "params".
 * API Platform only writes the type into the message text, where a client would have to parse it.
 */
final readonly class ExpectedTypeViolationFactory implements DenormalizationViolationFactoryInterface
{
    public function __construct(private DenormalizationViolationFactoryInterface $decorated)
    {
    }

    public function handle(
        NotNormalizableValueException|PartialDenormalizationException $exception,
        Operation $operation,
    ): void {
        try {
            $this->decorated->handle($exception, $operation);
        } catch (ValidationException $validationException) {
            $types = $this->expectedTypesByPath($exception);

            $violations = new ConstraintViolationList();
            foreach ($validationException->getConstraintViolationList() as $violation) {
                $parameters = $violation->getParameters();
                $type = $types[$violation->getPropertyPath()] ?? null;
                if (null !== $type && Type::INVALID_TYPE_ERROR === $violation->getCode()) {
                    $parameters['{{ type }}'] = $type;
                }

                $violations->add(new ConstraintViolation(
                    $violation->getMessage(),
                    $violation->getMessageTemplate(),
                    $parameters,
                    $violation->getRoot(),
                    $violation->getPropertyPath(),
                    $violation->getInvalidValue(),
                    $violation->getPlural(),
                    $violation->getCode(),
                    $violation instanceof ConstraintViolation ? $violation->getConstraint() : null,
                    $violation->getCause(),
                ));
            }

            throw new ValidationException($violations);
        }
    }

    /**
     * @return array<string, string> property path => expected types, as in the message ("int|null")
     */
    private function expectedTypesByPath(
        NotNormalizableValueException|PartialDenormalizationException $exception,
    ): array {
        $errors = $exception instanceof NotNormalizableValueException
            ? [$exception]
            : $exception->getNotNormalizableValueErrors();

        $types = [];
        foreach ($errors as $error) {
            $types[(string) $error->getPath()] = implode('|', $error->getExpectedTypes() ?? []);
        }

        return $types;
    }
}
