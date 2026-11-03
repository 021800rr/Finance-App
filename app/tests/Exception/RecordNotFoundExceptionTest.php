<?php

declare(strict_types=1);

namespace App\Tests\Exception;

use App\Exception\RecordNotFoundException;
use PHPUnit\Framework\TestCase;

final class RecordNotFoundExceptionTest extends TestCase
{
    public function testAsHttpIsANotFoundThatKeepsTheRecordForItsErrorCode(): void
    {
        $exception = RecordNotFoundException::bank();

        $http = $exception->asHttp();

        self::assertSame(404, $http->getStatusCode());
        self::assertSame('Bank not found.', $http->getMessage());
        self::assertSame($exception, $http->getPrevious());
        self::assertSame('bank.not_found', $exception->getErrorCode());
    }
}
