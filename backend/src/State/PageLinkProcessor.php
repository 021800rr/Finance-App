<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The addresses of the first, previous, next and last page of a list in the Link header (RFC 8288, with the
 * relation types of the IANA registry), next to the page itself (PageNormalizer).
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
final readonly class PageLinkProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(private ProcessorInterface $decorated)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $response = $this->decorated->process($data, $operation, $uriVariables, $context);

        $page = $context['original_data'] ?? null;
        $request = $context['request'] ?? null;
        if ($response instanceof Response && $page instanceof PaginatorInterface && $request instanceof Request) {
            $response->headers->set('Link', $this->links($request, $page));
        }

        return $response;
    }

    /**
     * @param PaginatorInterface<object> $page
     */
    private function links(Request $request, PaginatorInterface $page): string
    {
        $current = (int) $page->getCurrentPage();
        $last = (int) $page->getLastPage();
        $pages = ['first' => 1] + ($current > 1 ? ['prev' => $current - 1] : [])
            + ($current < $last ? ['next' => $current + 1] : []) + ['last' => $last];

        $links = [];
        foreach ($pages as $relation => $number) {
            $query = http_build_query(
                array_merge($request->query->all(), ['page' => $number]),
                encoding_type: PHP_QUERY_RFC3986,
            );
            $links[] = sprintf('<%s?%s>; rel="%s"', $request->getPathInfo(), $query, $relation);
        }

        return implode(', ', $links);
    }
}
