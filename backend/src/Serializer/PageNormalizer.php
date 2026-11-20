<?php

declare(strict_types=1);

namespace App\Serializer;

use ApiPlatform\State\Pagination\PaginatorInterface;
use App\State\Pagination\OffsetPaginator;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * One page of a list as plain JSON: the rows in "content" and the numbers of the page in "page", the shape of Spring
 * Data's PagedModel, so a Spring Boot backend gives the same. "number" is the page as ?page= names it (1 is the
 * first). The addresses of the other pages are in the Link header (PageLinkProcessor, RFC 8288).
 */
final class PageNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @param array<string, mixed> $context
     *
     * @return array{content: list<mixed>, page: array{size: int, number: int, totalElements: int, totalPages: int}}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (!$data instanceof PaginatorInterface) {
            throw new \InvalidArgumentException('Only a page of a list can be normalized here.');
        }

        $content = [];
        foreach ($data as $item) {
            $content[] = $this->normalizer->normalize($item, $format, $context);
        }

        return [
            'content' => $content,
            'page' => [
                'size' => (int) $data->getItemsPerPage(),
                'number' => (int) $data->getCurrentPage(),
                'totalElements' => (int) $data->getTotalItems(),
                'totalPages' => (int) $data->getLastPage(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof PaginatorInterface && 'json' === $format;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [OffsetPaginator::class => true];
    }
}
