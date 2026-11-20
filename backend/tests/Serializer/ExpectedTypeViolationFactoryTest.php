<?php

declare(strict_types=1);

namespace App\Tests\Serializer;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\DenormalizationViolationFactoryInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Serializer\ExpectedTypeViolationFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The API collects the wrong-type errors of all fields (collect_denormalization_errors), so its tests only ever pass
 * them all at once; an operation that stops at the first one would pass that single error.
 */
final class ExpectedTypeViolationFactoryTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function phpTypes(): iterable
    {
        yield 'whole number or null' => [['int', 'null'], 'number|null'];
        yield 'null or text, in JSON order' => [['null', 'string'], 'string|null'];
        yield 'decimal' => [['float'], 'number'];
        yield 'yes or no' => [['bool'], 'boolean'];
        yield 'list or an object of a class' => [['array', 'App\\Dto\\Money'], 'object|array'];
    }

    /**
     * @param list<string> $phpTypes
     */
    #[DataProvider('phpTypes')]
    public function testNamesTheExpectedJsonTypesInTheParameterAndTheMessage(array $phpTypes, string $json): void
    {
        $violation = $this->handled($phpTypes, 'This value should be of type {{ type }}.');

        self::assertSame('amount', $violation->getPropertyPath());
        self::assertSame(['{{ type }}' => $json], $violation->getParameters());
        self::assertSame('This value should be of type '.$json.'.', $violation->getMessage());
    }

    public function testKeepsTheMessageWhenThereIsNoTemplate(): void
    {
        self::assertSame('Wrong type.', $this->handled(['int'], null)->getMessage());
    }

    /**
     * @param list<string> $phpTypes
     */
    private function handled(array $phpTypes, ?string $template): ConstraintViolationInterface
    {
        $error = NotNormalizableValueException::createForUnexpectedDataType(
            'The type of the "amount" attribute must be "int", "string" given.',
            'abc',
            $phpTypes,
            'amount',
        );
        $decorated = $this->createStub(DenormalizationViolationFactoryInterface::class);
        $decorated->method('handle')->willThrowException(new ValidationException(new ConstraintViolationList([
            new ConstraintViolation(
                'Wrong type.',
                $template,
                [],
                null,
                'amount',
                'abc',
                code: Type::INVALID_TYPE_ERROR,
            ),
        ])));

        try {
            (new ExpectedTypeViolationFactory($decorated))->handle($error, new Post());
        } catch (ValidationException $exception) {
            return $exception->getConstraintViolationList()->get(0);
        }

        self::fail('The wrong-type error was not reported.');
    }
}
