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
 * Keeps the expected type of a wrong-type violation as a parameter, so ViolationCodes can expose it in "params".
 * API Platform only writes the type into the message text, where a client would have to parse it.
 *
 * The types are JSON's (RFC 8259: string, number, boolean, object, array, null), not PHP's ("int|null"): they
 * describe the request content, and a backend in another language names them the same way.
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
                $message = $violation->getMessage();
                if (null !== $type && Type::INVALID_TYPE_ERROR === $violation->getCode()) {
                    $parameters['{{ type }}'] = $type;
                    $template = $violation->getMessageTemplate();
                    $message = '' !== $template ? strtr($template, $parameters) : $message;
                }

                $violations->add(new ConstraintViolation(
                    $message,
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
     * @return array<string, string> property path => expected JSON types ("number|null")
     */
    private function expectedTypesByPath(
        NotNormalizableValueException|PartialDenormalizationException $exception,
    ): array {
        $errors = $exception instanceof NotNormalizableValueException
            ? [$exception]
            : $exception->getNotNormalizableValueErrors();

        $types = [];
        foreach ($errors as $error) {
            $types[(string) $error->getPath()] = self::jsonTypes(array_values($error->getExpectedTypes() ?? []));
        }

        return $types;
    }

    /**
     * @param list<string> $phpTypes as the serializer names them: "int", "string", "null", a class name…
     */
    private static function jsonTypes(array $phpTypes): string
    {
        $json = array_map(static fn (string $type): string => match ($type) {
            'int', 'float' => 'number',
            'string' => 'string',
            'bool' => 'boolean',
            'array' => 'array',
            'null' => 'null',
            default => 'object',
        }, $phpTypes);

        return implode('|', array_intersect(['string', 'number', 'boolean', 'object', 'array', 'null'], $json));
    }
}
