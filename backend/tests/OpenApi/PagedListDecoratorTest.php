<?php

declare(strict_types=1);

namespace App\Tests\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use App\OpenApi\PagedListDecorator;
use PHPUnit\Framework\TestCase;

final class PagedListDecoratorTest extends TestCase
{
    /**
     * Content given as a plain array (as in resource attributes) is wrapped like a MediaType.
     */
    public function testAPagedListGetsContentPageAndTheLinkHeader(): void
    {
        $ok = $this->documented(new Response('ok', new \ArrayObject([
            'application/json' => ['schema' => ['type' => 'array', 'items' => ['type' => 'string']]],
        ])));

        $media = $ok->getContent()['application/json'] ?? null;
        self::assertIsArray($media);
        $schema = $media['schema'] ?? null;
        self::assertIsArray($schema);
        $properties = $schema['properties'] ?? null;
        self::assertIsArray($properties);
        self::assertSame(['type' => 'array', 'items' => ['type' => 'string']], $properties['content'] ?? null);
        self::assertSame(['content', 'page'], $schema['required'] ?? null);
        self::assertArrayHasKey('Link', (array) $ok->getHeaders());
    }

    public function testAnAnswerWithoutContentIsLeftAsItIs(): void
    {
        $ok = new Response('ok');

        self::assertSame($ok, $this->documented($ok));
    }

    private function documented(Response $ok): Response
    {
        $operation = new Operation(
            responses: ['200' => $ok],
            parameters: [new Parameter('page', 'query')],
        );
        $paths = new Paths();
        $paths->addPath('/api/rows', new PathItem(get: $operation));
        $factory = $this->createStub(OpenApiFactoryInterface::class);
        $factory->method('__invoke')->willReturn(new OpenApi(new Info('Test', '1.0'), [], $paths));

        $openApi = (new PagedListDecorator($factory))();
        $response = $openApi->getPaths()->getPath('/api/rows')?->getGet()?->getResponses()['200'] ?? null;
        self::assertInstanceOf(Response::class, $response);

        return $response;
    }
}
