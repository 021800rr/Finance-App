<?php

declare(strict_types=1);

namespace App\Tests\Serializer;

use App\Serializer\PageNormalizer;
use App\State\Pagination\OffsetPaginator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class PageNormalizerTest extends TestCase
{
    public function testAPageIsItsRowsAndItsNumbers(): void
    {
        $rows = new OffsetPaginator([new \stdClass(), new \stdClass()], page: 2, itemsPerPage: 25, totalItems: 27);

        self::assertSame(
            [
                'content' => ['row', 'row'],
                'page' => ['size' => 25, 'number' => 2, 'totalElements' => 27, 'totalPages' => 2],
            ],
            $this->normalizer()->normalize($rows, 'json'),
        );
    }

    public function testOnlyAPageInPlainJsonIsItsBusiness(): void
    {
        $page = new OffsetPaginator([], 1, 25, 0);

        self::assertTrue($this->normalizer()->supportsNormalization($page, 'json'));
        self::assertFalse($this->normalizer()->supportsNormalization($page, 'csv'));
        self::assertFalse($this->normalizer()->supportsNormalization(new \stdClass(), 'json'));
    }

    public function testAnythingButAPageIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->normalizer()->normalize(new \stdClass(), 'json');
    }

    private function normalizer(): PageNormalizer
    {
        $rows = $this->createStub(NormalizerInterface::class);
        $rows->method('normalize')->willReturn('row');
        $normalizer = new PageNormalizer();
        $normalizer->setNormalizer($rows);

        return $normalizer;
    }
}
