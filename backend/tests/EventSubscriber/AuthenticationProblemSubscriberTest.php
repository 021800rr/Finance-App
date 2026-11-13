<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\AuthenticationProblemSubscriber;
use Gesdinet\JWTRefreshTokenBundle\Event\RefreshTokenNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\MissingTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class AuthenticationProblemSubscriberTest extends TestCase
{
    /**
     * The events are named by strings (the refresh token bundle has no constants for them): a typo in a handler's
     * name would only show when that error happens.
     */
    public function testEveryEventItListensToHasAHandler(): void
    {
        foreach (AuthenticationProblemSubscriber::getSubscribedEvents() as $event => $method) {
            self::assertIsString($method, $event);
            self::assertTrue(method_exists(AuthenticationProblemSubscriber::class, $method), $event);
        }
    }

    public function testAMissingRefreshTokenGetsItsOwnErrorCode(): void
    {
        $event = new RefreshTokenNotFoundEvent(new AuthenticationException());

        (new AuthenticationProblemSubscriber())->onRefreshTokenNotFound($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString('"errorCode":"auth.refresh_token_missing"', (string) $response->getContent());
    }

    public function testKeepsEveryHeaderOfTheBundlesResponse(): void
    {
        $original = new JWTAuthenticationFailureResponse('JWT Token not found');
        $original->headers->set('X-Added-By-Another-Listener', 'yes');
        $original->headers->setCookie(Cookie::create('BEARER', null, 1));
        $event = new JWTNotFoundEvent(new MissingTokenException(), $original, new Request());

        (new AuthenticationProblemSubscriber())->onTokenNotFound($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
        self::assertSame('yes', $response->headers->get('X-Added-By-Another-Listener'));
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $cookies = array_map(
            static fn (Cookie $cookie): string => $cookie->getName(),
            $response->headers->getCookies(),
        );
        self::assertSame(['BEARER'], $cookies);
    }
}
