<?php

declare(strict_types=1);

namespace App\Tests\OpenApi;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\OpenApi\OperationDescriptionMetadataFactory;
use PHPUnit\Framework\TestCase;

/**
 * API Platform builds this metadata once, when the Symfony cache is warmed, and the API tests read it from the cache:
 * this runs the factory itself, on one operation at a time.
 */
final class OperationDescriptionMetadataFactoryTest extends TestCase
{
    public function testGivesTheOpenApiDocsTheDescriptionOfTheOperation(): void
    {
        $openapi = $this->decorated(new Get(description: 'Retrieves one rate.'))->getOpenapi();

        self::assertInstanceOf(OpenApiOperation::class, $openapi);
        self::assertSame('Retrieves one rate.', $openapi->getDescription());
    }

    public function testAddsTheDescriptionAndKeepsTheOtherOpenApiSettings(): void
    {
        $operation = new Get(
            description: 'Retrieves one rate.',
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        );

        $openapi = $this->decorated($operation)->getOpenapi();

        self::assertInstanceOf(OpenApiOperation::class, $openapi);
        self::assertSame('Retrieves one rate.', $openapi->getDescription());
        self::assertSame([['JWT' => []]], $openapi->getSecurity());
    }

    public function testKeepsADescriptionWrittenForTheOpenApiDocs(): void
    {
        $operation = new Get(
            description: 'Retrieves one rate.',
            openapi: new OpenApiOperation(description: 'Written for Swagger.'),
        );

        $openapi = $this->decorated($operation)->getOpenapi();

        self::assertInstanceOf(OpenApiOperation::class, $openapi);
        self::assertSame('Written for Swagger.', $openapi->getDescription());
    }

    public function testLeavesAnOperationHiddenFromTheDocsHidden(): void
    {
        $operation = new Get(description: 'Retrieves one rate.', openapi: false);

        self::assertFalse($this->decorated($operation)->getOpenapi());
    }

    public function testLeavesAnOperationWithoutADescriptionAlone(): void
    {
        self::assertNull($this->decorated(new Get())->getOpenapi());
    }

    public function testLeavesAResourceWithoutOperationsAlone(): void
    {
        $resource = new ApiResource();

        $collection = $this->factory($resource)->create(self::class);

        self::assertSame($resource, $collection[0]);
    }

    private function decorated(HttpOperation $operation): HttpOperation
    {
        $collection = $this->factory(new ApiResource(operations: [$operation]))->create(self::class);

        $operations = $collection[0]?->getOperations();
        self::assertNotNull($operations);
        foreach ($operations as $decorated) {
            self::assertInstanceOf(HttpOperation::class, $decorated);

            return $decorated;
        }

        self::fail('The resource lost its operation.');
    }

    private function factory(ApiResource $resource): OperationDescriptionMetadataFactory
    {
        $decorated = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $decorated->method('create')->willReturn(new ResourceMetadataCollection(self::class, [$resource]));

        return new OperationDescriptionMetadataFactory($decorated);
    }
}
