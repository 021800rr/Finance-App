<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;

/**
 * API Platform puts an operation's "description" only into the Hydra docs; the OpenAPI (Swagger) docs
 * read just openapi.description and otherwise show a generic "Creates a X resource." text.
 * This copies the operation description into the OpenAPI operation when that one has none of its own.
 */
final readonly class OperationDescriptionMetadataFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(private ResourceMetadataCollectionFactoryInterface $decorated)
    {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);

        foreach ($collection as $index => $resource) {
            $operations = $resource->getOperations();
            if (null === $operations) {
                continue;
            }

            foreach ($operations as $name => $operation) {
                $operations->add($name, $this->withOpenApiDescription($operation));
            }

            $collection[$index] = $resource->withOperations($operations);
        }

        return $collection;
    }

    private function withOpenApiDescription(HttpOperation $operation): HttpOperation
    {
        $description = $operation->getDescription();
        $openapi = $operation->getOpenapi();
        if (null === $description || false === $openapi) {
            return $operation;
        }

        if (null === $openapi || true === $openapi) {
            return $operation->withOpenapi(new OpenApiOperation(description: $description));
        }

        if ($openapi instanceof OpenApiOperation && null === $openapi->getDescription()) {
            return $operation->withOpenapi($openapi->withDescription($description));
        }

        return $operation;
    }
}
