<?php

declare(strict_types=1);

namespace App\Tests\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\Model\Schema;
use ApiPlatform\OpenApi\OpenApi;
use App\Http\ProblemResponse;
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

    public function testAnErrorResponseGetsAnExampleOfItsStatus(): void
    {
        $schema = $this->documentedSchema('404', ['$ref' => '#/components/schemas/Error']);

        $allOf = $schema['allOf'] ?? null;
        self::assertIsArray($allOf);
        self::assertSame(['$ref' => '#/components/schemas/Error'], $allOf[0] ?? null);
        self::assertSame(
            ['type' => 'object', 'properties' => [
                'status' => ['type' => 'integer', 'const' => 404, 'examples' => [404]],
                'errorCode' => ['type' => 'string', 'examples' => [ProblemResponse::httpErrorCode(404)]],
            ]],
            $allOf[1] ?? null,
        );
    }

    /**
     * Only a response that is one of the error schemas gets the example; any other content of an error status is
     * described by someone who knew better.
     */
    public function testLeavesAnErrorStatusWithAnotherSchemaAsItIs(): void
    {
        $schema = ['type' => 'object', 'properties' => ['reason' => ['type' => 'string']]];

        self::assertSame($schema, $this->documentedSchema('409', $schema));
    }

    public function testLeavesAChoiceThatIsNotOnlyErrorSchemasAsItIs(): void
    {
        $schema = ['anyOf' => [
            ['$ref' => '#/components/schemas/ConstraintViolation'],
            ['$ref' => '#/components/schemas/Other'],
        ]];

        self::assertSame($schema, $this->documentedSchema('422', $schema));
    }

    /**
     * The schema of the response with the status, after the decorator, of an operation whose only response it is.
     *
     * @param array<mixed> $schema
     *
     * @return array<mixed>
     */
    private function documentedSchema(string $status, array $schema): array
    {
        $paths = new Paths();
        $paths->addPath('/api/things', new PathItem(get: new Operation(responses: [
            $status => new Response('An error.', new \ArrayObject(['application/json' => ['schema' => $schema]])),
        ])));
        $openApi = new OpenApi(new Info('Test', '1.0'), [], $paths, new Components(new \ArrayObject()));
        $factory = $this->createStub(OpenApiFactoryInterface::class);
        $factory->method('__invoke')->willReturn($openApi);

        $responses = (new ProblemDetailsDecorator($factory))()->getPaths()->getPath('/api/things')?->getGet()
            ?->getResponses();
        $response = $responses[$status] ?? null;
        self::assertInstanceOf(Response::class, $response);
        $media = $response->getContent()['application/json'] ?? null;
        self::assertIsArray($media);
        self::assertIsArray($media['schema'] ?? null);

        return $media['schema'];
    }
}
