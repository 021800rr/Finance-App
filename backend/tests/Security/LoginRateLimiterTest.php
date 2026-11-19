<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\DataFixtures\AppFixtures;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The web form and the API log in to the same accounts, so they count the failed attempts at an account together:
 * five in one lock the other as well.
 */
final class LoginRateLimiterTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // The limiter lives in the kernel's memory in tests (config/packages/test/cache.yaml).
        $this->client->disableReboot();
        $this->initEntityManager($this->client->getKernel());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testALockInTheWebFormLocksTheApi(): void
    {
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->logInThroughTheWebForm('wrong-password-'.$attempt);
        }

        $this->logInThroughTheApi(AppFixtures::TEST_USER_PASSWORD);

        self::assertResponseStatusCodeSame(401);
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('auth.too_many_login_attempts', $content);
        self::assertStringNotContainsString('"token"', $content);
    }

    public function testALockInTheApiLocksTheWebForm(): void
    {
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->logInThroughTheApi('wrong-password-'.$attempt);
        }

        $this->logInThroughTheWebForm(AppFixtures::TEST_USER_PASSWORD);
        $crawler = $this->client->followRedirect();

        self::assertStringContainsString('Too many failed login attempts', $crawler->filter('.alert-danger')->text());
    }

    public function testAttemptsInBothAddUpToTheLock(): void
    {
        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            $this->logInThroughTheWebForm('wrong-password-'.$attempt);
        }
        for ($attempt = 4; $attempt <= 5; ++$attempt) {
            $this->logInThroughTheApi('wrong-password-'.$attempt);
        }

        $this->logInThroughTheApi(AppFixtures::TEST_USER_PASSWORD);

        self::assertResponseStatusCodeSame(401);
        self::assertStringContainsString(
            'auth.too_many_login_attempts',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    /**
     * Only failed attempts count: more successful logins than the per-IP limit (25 in 15 minutes) still log in.
     */
    public function testSuccessfulLoginsDoNotCountTowardsTheLock(): void
    {
        for ($login = 1; $login <= 30; ++$login) {
            $this->logInThroughTheApi(AppFixtures::TEST_USER_PASSWORD);

            self::assertResponseStatusCodeSame(200, "Login {$login}");
        }
        self::assertStringContainsString('"token"', (string) $this->client->getResponse()->getContent());
    }

    /**
     * A successful login clears the failed attempts at the account: four, a login, four more still log in.
     */
    public function testASuccessfulLoginClearsTheFailedAttemptsAtTheAccount(): void
    {
        for ($round = 1; $round <= 2; ++$round) {
            for ($attempt = 1; $attempt <= 4; ++$attempt) {
                $this->logInThroughTheApi("wrong-password-{$round}-{$attempt}");
            }

            $this->logInThroughTheApi(AppFixtures::TEST_USER_PASSWORD);

            self::assertResponseStatusCodeSame(200, "Round {$round}");
        }
    }

    public function testFailedAttemptsStillLockAfterSuccessfulLogins(): void
    {
        for ($login = 1; $login <= 10; ++$login) {
            $this->logInThroughTheApi(AppFixtures::TEST_USER_PASSWORD);
        }
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->logInThroughTheApi('wrong-password-'.$attempt);
        }

        $this->logInThroughTheApi(AppFixtures::TEST_USER_PASSWORD);

        self::assertResponseStatusCodeSame(401);
        self::assertStringContainsString(
            'auth.too_many_login_attempts',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    private function logInThroughTheWebForm(string $password): void
    {
        $form = $this->client->request('GET', '/login')->filter('form')->form();
        $form['_username'] = AppFixtures::TEST_USER_EMAIL;
        $form['_password'] = $password;
        $this->client->submit($form);
    }

    private function logInThroughTheApi(string $password): void
    {
        $this->client->jsonRequest('POST', '/api/login_check', [
            'email' => AppFixtures::TEST_USER_EMAIL,
            'password' => $password,
        ]);
    }
}
