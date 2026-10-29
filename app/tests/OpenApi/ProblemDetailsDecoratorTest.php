<?php

declare(strict_types=1);

namespace App\Tests\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\Schema;
use ApiPlatform\OpenApi\OpenApi;
use App\OpenApi\ProblemDetailsDecorator;
use PHPUnit\Framework\TestCase;

final class ProblemDetailsDecoratorTest extends TestCase
{
    public function testSchemaWithoutPropertiesOrAllOfIsLeftAsItIs(): void
    {
        // API Platform passes plain ArrayObject-based schemas (JsonSchema\Schema) at runtime, too.
        /** @var \ArrayObject<string, Schema> $schemas */
        $schemas = new \ArrayObject([
            'Error' => new \ArrayObject(['$ref' => '#/components/schemas/Other']),
            'ConstraintViolation' => new \ArrayObject(['type' => 'object', 'properties' => ['status' => []]]),
        ]);
        $openApi = new OpenApi(new Info('Test', '1.0'), [], new Paths(), new Components($schemas));
        $factory = $this->createStub(OpenApiFactoryInterface::class);
        $factory->method('__invoke')->willReturn($openApi);

        $result = (new ProblemDetailsDecorator($factory))();

        $documented = $result->getComponents()->getSchemas();
        self::assertNotNull($documented);
        self::assertInstanceOf(\ArrayObject::class, $documented['Error']);
        self::assertSame(['$ref' => '#/components/schemas/Other'], $documented['Error']->getArrayCopy());
        self::assertInstanceOf(\ArrayObject::class, $documented['ConstraintViolation']);
        $properties = $documented['ConstraintViolation']['properties'];
        self::assertIsArray($properties);
        self::assertArrayHasKey('errorCode', $properties);
    }
}
