<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Util\EntityOrThrow;
use PHPUnit\Framework\TestCase;

final class EntityOrThrowTest extends TestCase
{
    public function testRequireReturnsTheEntityWhenPresent(): void
    {
        $entity = new \stdClass();

        self::assertSame($entity, EntityOrThrow::require($entity, 'not used'));
    }

    public function testRequireThrowsWhenEntityIsNull(): void
    {
        /** @var \stdClass|null $entity */
        $entity = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('entity not found');

        EntityOrThrow::require($entity, 'entity not found');
    }
}
