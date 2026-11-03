<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\ApiPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApiPathTest extends TestCase
{
    #[DataProvider('paths')]
    public function testOnlyTheApiPrefixAsAWholeSegmentMatches(string $path, bool $matches): void
    {
        self::assertSame($matches, ApiPath::matches($path));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function paths(): iterable
    {
        yield 'the prefix itself' => ['/api', true];
        yield 'a resource' => ['/api/fixed_expenses/5', true];
        yield 'the login' => ['/api/login_check', true];
        yield 'a longer word' => ['/apis', false];
        yield 'a hyphenated word' => ['/api-help', false];
        yield 'a web page' => ['/operation/new', false];
        yield 'the prefix later in the path' => ['/docs/api', false];
    }
}
