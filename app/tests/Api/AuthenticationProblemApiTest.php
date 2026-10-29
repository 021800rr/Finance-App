<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Authentication failures are answered by the JWT bundle, outside API Platform; they still use the API's
 * Problem Details shape with a stable "errorCode", keeping status 401 and the WWW-Authenticate header.
 */
final class AuthenticationProblemApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testMissingToken(): void
    {
        $this->client->request('GET', '/api/accounts');

        $this->assertAuthenticationProblem('auth.token_missing');
    }

    public function testInvalidToken(): void
    {
        $this->client->request('GET', '/api/accounts', server: ['HTTP_AUTHORIZATION' => 'Bearer abc.def.ghi']);

        $this->assertAuthenticationProblem('auth.token_invalid');
    }

    public function testExpiredToken(): void
    {
        $encoder = self::getContainer()->get('lexik_jwt_authentication.encoder');
        self::assertInstanceOf(JWTEncoderInterface::class, $encoder);
        $token = $encoder->encode(['username' => 'test@example.com', 'iat' => time() - 7200, 'exp' => time() - 3600]);

        $this->client->request('GET', '/api/accounts', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        $this->assertAuthenticationProblem('auth.token_expired');
    }

    public function testWrongPassword(): void
    {
        $this->clearLoginThrottle();

        $this->login('wrong-password');

        $problem = $this->assertAuthenticationProblem('auth.invalid_credentials');
        self::assertSame('Invalid credentials.', $problem['detail'] ?? null);
    }

    public function testTooManyFailedLoginsNameTheLockTime(): void
    {
        $this->clearLoginThrottle();

        try {
            for ($attempt = 1; $attempt <= 5; ++$attempt) {
                $this->login('wrong-password-'.$attempt);
            }
            $this->login('test1234');

            $problem = $this->assertAuthenticationProblem('auth.too_many_login_attempts', ['minutes' => '15']);
            self::assertResponseHeaderSame('Retry-After', '900');
            self::assertSame(
                'Too many failed login attempts, please try again in 15 minutes.',
                $problem['detail'] ?? null,
            );
        } finally {
            $this->clearLoginThrottle();
        }
    }

    /**
     * @param array<string, string> $params
     *
     * @return array<mixed>
     */
    private function assertAuthenticationProblem(string $errorCode, array $params = []): array
    {
        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        $content = (string) $this->client->getResponse()->getContent();
        $problem = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($problem);
        self::assertSame(401, $problem['status'] ?? null);
        self::assertSame('Unauthorized', $problem['title'] ?? null);
        self::assertSame($errorCode, $problem['errorCode'] ?? null);
        self::assertSame('urn:finance-app:problem:'.$errorCode, $problem['type'] ?? null);
        self::assertSame($params, $problem['params'] ?? null);
        self::assertIsString($problem['detail'] ?? null);
        if ([] === $params) {
            self::assertStringContainsString('"params":{}', $content);
        }

        return $problem;
    }

    private function login(string $password): void
    {
        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'test@example.com', 'password' => $password], JSON_THROW_ON_ERROR),
        );
    }

    /**
     * The limiter keeps its counters in a persistent cache pool: reset it so a test neither inherits nor leaves
     * behind failed attempts.
     */
    private function clearLoginThrottle(): void
    {
        $pool = self::getContainer()->get('cache.rate_limiter');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $pool->clear();
    }
}
