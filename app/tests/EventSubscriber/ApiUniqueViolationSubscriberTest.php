<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ApiUniqueViolationSubscriber;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiUniqueViolationSubscriberTest extends TestCase
{
    /**
     * Above 0: the conflict replaces the SQL error before Symfony's ErrorListener logs it (priority 0) and before
     * API Platform answers, so neither the log nor the answer shows the SQL.
     */
    public function testReplacesTheExceptionBeforeItIsLoggedAndAnswered(): void
    {
        $listener = ApiUniqueViolationSubscriber::getSubscribedEvents()[KernelEvents::EXCEPTION] ?? null;
        self::assertIsArray($listener);
        $priority = $listener[1] ?? null;

        self::assertSame('onException', $listener[0] ?? null);
        self::assertIsInt($priority);
        self::assertGreaterThan(0, $priority);
    }

    #[DataProvider('apiPaths')]
    public function testUniqueViolationUnderApiBecomesAConflictWithoutSql(string $path): void
    {
        $event = $this->event($path, $this->uniqueViolation());

        (new ApiUniqueViolationSubscriber())->onException($event);

        $exception = $event->getThrowable();
        self::assertInstanceOf(ConflictHttpException::class, $exception);
        self::assertSame('This record already exists.', $exception->getMessage());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function apiPaths(): iterable
    {
        yield 'api root' => ['/api'];
        yield 'api resource' => ['/api/fixed_expenses'];
    }

    #[DataProvider('otherPaths')]
    public function testOtherPathsAreLeftAlone(string $path): void
    {
        $uniqueViolation = $this->uniqueViolation();
        $event = $this->event($path, $uniqueViolation);

        (new ApiUniqueViolationSubscriber())->onException($event);

        self::assertSame($uniqueViolation, $event->getThrowable());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function otherPaths(): iterable
    {
        yield 'web page' => ['/contractor/create'];
        yield 'path only starting like the api' => ['/apis'];
    }

    public function testOtherExceptionsAreLeftAlone(): void
    {
        $exception = new \RuntimeException('Something else.');
        $event = $this->event('/api/fixed_expenses', $exception);

        (new ApiUniqueViolationSubscriber())->onException($event);

        self::assertSame($exception, $event->getThrowable());
    }

    private function event(string $path, \Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            self::createStub(HttpKernelInterface::class),
            Request::create($path),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }

    private function uniqueViolation(): UniqueConstraintViolationException
    {
        return new UniqueConstraintViolationException(
            new class('SQLSTATE[23505]') extends \Exception implements DriverException {
                public function getSQLState(): string
                {
                    return '23505';
                }
            },
            null,
        );
    }
}
