<?php

declare(strict_types=1);

namespace App\Tests\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\OpenApi;
use App\OpenApi\JwtDecorator;
use PHPUnit\Framework\TestCase;

final class JwtDecoratorTest extends TestCase
{
    /**
     * OpenAPI 3.1 has no "nullable": false says no more than the type, true is the type "null" as well.
     */
    public function testNullableBecomesTheTypeNull(): void
    {
        $body = new RequestBody(content: new \ArrayObject(['application/json' => new MediaType(new \ArrayObject([
            'type' => 'object',
            'properties' => [
                'kept' => ['type' => 'string', 'nullable' => false],
                'optional' => ['type' => 'string', 'nullable' => true],
            ],
        ]))]));
        $paths = new Paths();
        // As the token bundles give it: the response as a plain array.
        $paths->addPath('/api/things', new PathItem(post: (new Operation(requestBody: $body))->withResponses([
            '200' => ['description' => 'ok', 'content' => ['application/json' => ['schema' => [
                'type' => 'string',
                'nullable' => true,
            ]]]],
        ])));
        $factory = $this->createStub(OpenApiFactoryInterface::class);
        $factory->method('__invoke')->willReturn(new OpenApi(new Info('Test', '1.0'), [], $paths));

        $post = (new JwtDecorator($factory))()->getPaths()->getPath('/api/things')?->getPost();

        $schema = $post?->getRequestBody()?->getContent()?->offsetGet('application/json');
        self::assertInstanceOf(MediaType::class, $schema);
        self::assertSame(
            ['kept' => ['type' => 'string'], 'optional' => ['type' => ['string', 'null']]],
            $schema->getSchema()?->getArrayCopy()['properties'] ?? null,
        );
        self::assertSame(
            ['description' => 'ok', 'content' => ['application/json' => ['schema' => ['type' => ['string', 'null']]]]],
            $post?->getResponses()['200'] ?? null,
        );
    }

    public function testAnOperationWithoutResponsesStaysWithout(): void
    {
        $paths = new Paths();
        $paths->addPath('/api/things', new PathItem(get: new Operation(operationId: 'things')));
        $factory = $this->createStub(OpenApiFactoryInterface::class);
        $factory->method('__invoke')->willReturn(new OpenApi(new Info('Test', '1.0'), [], $paths));

        $get = (new JwtDecorator($factory))()->getPaths()->getPath('/api/things')?->getGet();

        self::assertSame('things', $get?->getOperationId());
        self::assertNull($get->getResponses());
    }
}
