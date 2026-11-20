<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Documents a page of a list as PageNormalizer and PageLinkProcessor give it: "content" and "page" in the body, the
 * other pages in the Link header. API Platform describes every list as a plain array. A list that pages is the one
 * with the "page" query parameter.
 */
final readonly class PagedListDecorator implements OpenApiFactoryInterface
{
    private const string LINK_DESCRIPTION = 'The first, previous, next and last page (RFC 8288), e.g. '
        .'</api/accounts/1/operations?page=2>; rel="next". "prev" only after the first page, "next" only before the '
        .'last one.';

    public function __construct(private OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        // Paths::addPath() takes nothing but a PathItem; getPaths() just does not say so in its type.
        /** @var array<string, PathItem> $pathItems */
        $pathItems = $openApi->getPaths()->getPaths();
        foreach ($pathItems as $path => $pathItem) {
            $get = $pathItem->getGet();
            if (null !== $get && $this->pages($get)) {
                $openApi->getPaths()->addPath($path, $pathItem->withGet($this->withPagedResponse($get)));
            }
        }

        return $openApi;
    }

    private function pages(Operation $operation): bool
    {
        foreach ($operation->getParameters() ?? [] as $parameter) {
            if ('page' === $parameter->getName() && 'query' === $parameter->getIn()) {
                return true;
            }
        }

        return false;
    }

    private function withPagedResponse(Operation $operation): Operation
    {
        $responses = $operation->getResponses() ?? [];
        $ok = $responses['200'] ?? null;
        $content = $ok instanceof Response ? $ok->getContent() : null;
        if (!$ok instanceof Response || null === $content) {
            return $operation;
        }

        /** @var \ArrayObject<string, mixed> $paged */
        $paged = new \ArrayObject();
        foreach ($content as $mediaType => $media) {
            // A MediaType, or a plain array where a resource attribute gave the content.
            $listed = is_array($media) ? $media['schema'] ?? null : null;
            $schema = $media instanceof MediaType ? $media->getSchema() : $listed;
            $paged[(string) $mediaType] = ['schema' => [
                'type' => 'object',
                'properties' => [
                    'content' => $schema instanceof \ArrayObject ? $schema->getArrayCopy() : $schema,
                    'page' => [
                        'type' => 'object',
                        'properties' => [
                            'size' => ['type' => 'integer', 'description' => 'Rows per page.', 'example' => 25],
                            'number' => [
                                'type' => 'integer',
                                'description' => 'This page, as ?page= names it: 1 is the first.',
                                'example' => 1,
                            ],
                            'totalElements' => [
                                'type' => 'integer',
                                'description' => 'Rows on all pages.',
                                'example' => 30,
                            ],
                            'totalPages' => ['type' => 'integer', 'description' => 'Number of pages.', 'example' => 2],
                        ],
                        'required' => ['size', 'number', 'totalElements', 'totalPages'],
                    ],
                ],
                'required' => ['content', 'page'],
            ]];
        }
        $responses['200'] = $ok
            ->withDescription('One page of the list.')
            ->withContent($paged)
            ->withHeaders(new \ArrayObject([
                'Link' => ['description' => self::LINK_DESCRIPTION, 'schema' => ['type' => 'string']],
            ]));

        return $operation->withResponses($responses);
    }
}
