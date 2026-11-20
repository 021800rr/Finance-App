<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\AuthenticationProblemSubscriber;
use Gesdinet\JWTRefreshTokenBundle\Event\RefreshTokenNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
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

    public function testAMissingRefreshTokenGetsItsOwnType(): void
    {
        $event = new RefreshTokenNotFoundEvent(new AuthenticationException());

        (new AuthenticationProblemSubscriber())->onRefreshTokenNotFound($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString(
            '"type":"urn:finance-app:problem:auth.refresh_token_missing","title":"Missing refresh token"',
            (string) $response->getContent(),
        );
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

    public function testARefusedTokenGetsTheInvalidTokenChallengeAndKeepsTheOtherHeaders(): void
    {
        $subscriber = new AuthenticationProblemSubscriber();
        $invalid = new JWTInvalidEvent(new InvalidTokenException(), $this->bundleResponse(), new Request());
        $expired = new JWTExpiredEvent(new ExpiredTokenException(), $this->bundleResponse(), new Request());

        $subscriber->onTokenInvalid($invalid);
        $subscriber->onTokenExpired($expired);

        foreach ([$invalid->getResponse(), $expired->getResponse()] as $response) {
            self::assertNotNull($response);
            self::assertSame(401, $response->getStatusCode());
            self::assertSame(['Bearer error="invalid_token"'], $response->headers->all('WWW-Authenticate'));
            self::assertSame('yes', $response->headers->get('X-Added-By-Another-Listener'));
        }
    }

    private function bundleResponse(): JWTAuthenticationFailureResponse
    {
        $response = new JWTAuthenticationFailureResponse('Invalid JWT Token');
        $response->headers->set('X-Added-By-Another-Listener', 'yes');

        return $response;
    }
}
