<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Util\RequestBody;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestBodyTest extends TestCase
{
    /**
     * @param array<mixed>|null $expected
     */
    #[DataProvider('bodies')]
    public function testDecodesOnlyAJsonObjectOrList(string $content, ?array $expected): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/api/transfers', 'POST', content: $content));

        self::assertSame($expected, RequestBody::decode($requestStack));
    }

    /**
     * @return iterable<string, array{string, array<mixed>|null}>
     */
    public static function bodies(): iterable
    {
        yield 'object, a sent null kept' => ['{"amountTo":null,"isMain":true}', ['amountTo' => null, 'isMain' => true]];
        yield 'empty object' => ['{}', []];
        yield 'list' => ['[1,2]', [1, 2]];
        yield 'number' => ['5', null];
        yield 'malformed' => ['{bad', null];
        yield 'empty' => ['', null];
    }

    public function testThereIsNoBodyWithoutARequest(): void
    {
        self::assertNull(RequestBody::decode(new RequestStack()));
    }
}
