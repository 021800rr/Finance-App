<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\DataFixtures\AppFixtures;
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
        // The limiter lives in the kernel's memory in tests (config/packages/test/cache.yaml).
        $this->client->disableReboot();
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->requestLogin(AppFixtures::TEST_USER_EMAIL, 'wrong-password-'.$attempt);
        }

        $this->requestLogin(AppFixtures::TEST_USER_EMAIL, AppFixtures::TEST_USER_PASSWORD);

        self::assertResponseStatusCodeSame(401);
        self::assertStringNotContainsString('"token"', (string) $this->client->getResponse()->getContent());
    }

    public function testAnotherSpellingOfTheLockedAddressIsLockedToo(): void
    {
        // The limiter lives in the kernel's memory in tests (config/packages/test/cache.yaml).
        $this->client->disableReboot();
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->requestLogin(AppFixtures::TEST_USER_EMAIL, 'wrong-password-'.$attempt);
        }

        // Each of these logs in to the same account, so each must meet the same lock.
        foreach ([' '.AppFixtures::TEST_USER_EMAIL, AppFixtures::TEST_USER_EMAIL.'  ', 'TEST@Example.COM'] as $email) {
            $this->requestLogin($email, AppFixtures::TEST_USER_PASSWORD);

            self::assertResponseStatusCodeSame(401, $email);
            self::assertStringNotContainsString('"token"', (string) $this->client->getResponse()->getContent());
        }
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
