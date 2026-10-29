<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ApiExceptionProblemSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\EventListener\ErrorListener;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiExceptionProblemSubscriberTest extends KernelTestCase
{
    /**
     * Setting a response stops the event: Symfony must have logged the exception (and the profiler recorded it)
     * before this subscriber answers, or server errors of these routes would leave no trace in the logs.
     */
    public function testRunsAfterTheExceptionIsLogged(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $order = [];
        foreach ($dispatcher->getListeners(KernelEvents::EXCEPTION) as $listener) {
            if (is_array($listener) && is_object($listener[0])) {
                $order[] = $listener[0]::class.'::'.$listener[1];
            }
        }

        $logs = array_search(ErrorListener::class.'::logKernelException', $order, true);
        $answers = array_search(ApiExceptionProblemSubscriber::class.'::onException', $order, true);
        self::assertIsInt($logs);
        self::assertIsInt($answers);
        self::assertLessThan($answers, $logs);
    }

    public function testKeepsNonStringHeadersSuchAsRetryAfter(): void
    {
        $event = $this->exceptionEvent('/api/login_check', new TooManyRequestsHttpException(60));

        (new ApiExceptionProblemSubscriber(false))->onException($event);

        self::assertSame(429, $event->getResponse()?->getStatusCode());
        self::assertSame('60', $event->getResponse()->headers->get('Retry-After'));
    }

    #[DataProvider('paths')]
    public function testAnswersOnlyUnderTheApiPath(string $path, bool $answered): void
    {
        $event = $this->exceptionEvent($path, new \RuntimeException('Something broke'));

        (new ApiExceptionProblemSubscriber(false))->onException($event);

        self::assertSame($answered, null !== $event->getResponse());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function paths(): iterable
    {
        yield '/api' => ['/api', true];
        yield '/api/login_check' => ['/api/login_check', true];
        yield '/apiary' => ['/apiary', false];
        yield '/api-help' => ['/api-help', false];
        yield 'web page' => ['/operation/new', false];
    }

    public function testServerErrorRevealsNothingOutsideDebug(): void
    {
        $event = $this->exceptionEvent('/api/login_check', new \RuntimeException('SQLSTATE[08006] connection failed'));

        (new ApiExceptionProblemSubscriber(false))->onException($event);

        $content = (string) $event->getResponse()?->getContent();
        self::assertStringContainsString('"errorCode":"http.internal_server_error"', $content);
        self::assertStringNotContainsString('SQLSTATE', $content);
    }

    public function testClientErrorWithoutAMessageGetsTheStatusAsDetail(): void
    {
        $event = $this->exceptionEvent('/api/login_check', new BadRequestHttpException());

        (new ApiExceptionProblemSubscriber(false))->onException($event);

        self::assertStringContainsString('"detail":"Bad Request."', (string) $event->getResponse()?->getContent());
    }

    public function testServerErrorInDebugModeSaysWhereItHappened(): void
    {
        $exception = new \RuntimeException('Something broke');
        $event = $this->exceptionEvent('/api/login_check', $exception);

        (new ApiExceptionProblemSubscriber(true))->onException($event);

        $problem = json_decode((string) $event->getResponse()?->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($problem);
        self::assertSame(
            sprintf('RuntimeException: Something broke (%s:%d)', $exception->getFile(), $exception->getLine()),
            $problem['detail'] ?? null,
        );
    }

    private function exceptionEvent(string $path, \Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create($path, 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }
}
