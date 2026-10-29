<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\AuthenticationProblemSubscriber;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\MissingTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

final class AuthenticationProblemSubscriberTest extends TestCase
{
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
