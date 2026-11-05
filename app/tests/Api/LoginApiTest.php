<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\DataFixtures\AppFixtures;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testLoginCheckReturnsJwtTokenForValidCredentials(): void
    {
        $this->requestLogin(AppFixtures::TEST_USER_EMAIL, AppFixtures::TEST_USER_PASSWORD);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"token"', (string) $this->client->getResponse()->getContent());
    }

    public function testLoginCheckWithInvalidCredentialsFails(): void
    {
        $this->requestLogin(AppFixtures::TEST_USER_EMAIL, 'wrongpassword');

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginCheckAcceptsTheEmailInAnyCaseWithSurroundingSpaces(): void
    {
        $this->requestLogin('  TEST@Example.COM ', AppFixtures::TEST_USER_PASSWORD);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"token"', (string) $this->client->getResponse()->getContent());
    }

    public function testLoginCheckRefusesEvenTheCorrectPasswordAfterTooManyFailedAttempts(): void
    {
        $this->clearLoginThrottle();

        try {
            for ($attempt = 1; $attempt <= 5; ++$attempt) {
                $this->requestLogin(AppFixtures::TEST_USER_EMAIL, 'wrong-password-'.$attempt);
            }

            $this->requestLogin(AppFixtures::TEST_USER_EMAIL, AppFixtures::TEST_USER_PASSWORD);

            self::assertResponseStatusCodeSame(401);
            self::assertStringNotContainsString('"token"', (string) $this->client->getResponse()->getContent());
        } finally {
            $this->clearLoginThrottle();
        }
    }

    /**
     * The limiter keeps its counters in a persistent cache pool: reset it so this test neither inherits nor leaves
     * behind failed attempts.
     */
    private function clearLoginThrottle(): void
    {
        $pool = self::getContainer()->get('cache.rate_limiter');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $pool->clear();
    }

    private function requestLogin(string $email, string $password): void
    {
        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR),
        );
    }
}
