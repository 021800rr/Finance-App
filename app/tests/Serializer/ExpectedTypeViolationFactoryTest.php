<?php

declare(strict_types=1);

namespace App\Tests\Serializer;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\DenormalizationViolationFactoryInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Serializer\ExpectedTypeViolationFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The API collects the wrong-type errors of all fields (collect_denormalization_errors), so its tests only ever pass
 * them all at once; an operation that stops at the first one would pass that single error.
 */
final class ExpectedTypeViolationFactoryTest extends TestCase
{
    public function testKeepsTheExpectedTypeOfASingleWrongTypeError(): void
    {
        $error = NotNormalizableValueException::createForUnexpectedDataType(
            'The type of the "amount" attribute must be "int", "string" given.',
            'abc',
            ['int', 'null'],
            'amount',
        );
        $decorated = $this->createStub(DenormalizationViolationFactoryInterface::class);
        $decorated->method('handle')->willThrowException(new ValidationException(new ConstraintViolationList([
            new ConstraintViolation('Wrong type.', null, [], null, 'amount', 'abc', null, Type::INVALID_TYPE_ERROR),
        ])));

        try {
            (new ExpectedTypeViolationFactory($decorated))->handle($error, new Post());
            self::fail('The wrong-type error was not reported.');
        } catch (ValidationException $exception) {
            $violation = $exception->getConstraintViolationList()->get(0);
            self::assertSame('amount', $violation->getPropertyPath());
            self::assertSame(['{{ type }}' => 'int|null'], $violation->getParameters());
        }
    }
}
