<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginControllerTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->initEntityManager($this->client->getKernel());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testLoginPageRendersSuccessfully(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testLoginWithInvalidCredentialsShowsError(): void
    {
        $this->submitLoginForm(AppFixtures::TEST_USER_EMAIL, 'wrongpassword');
        $crawler = $this->client->followRedirect();

        self::assertStringContainsString('Invalid credentials', $crawler->filter('.alert-danger')->text());
    }

    public function testLoginWithValidCredentialsRedirectsToHome(): void
    {
        $this->submitLoginForm(AppFixtures::TEST_USER_EMAIL, AppFixtures::TEST_USER_PASSWORD);

        self::assertResponseRedirects('/');
    }

    public function testLoginThrottlesAfterTooManyFailedAttempts(): void
    {
        // The limiter lives in the kernel's memory in tests (config/packages/test/cache.yaml).
        $this->client->disableReboot();
        for ($i = 0; $i < 5; ++$i) {
            $this->submitLoginForm(AppFixtures::TEST_USER_EMAIL, 'wrongpassword');
        }

        $this->submitLoginForm(AppFixtures::TEST_USER_EMAIL, 'wrongpassword');
        $crawler = $this->client->followRedirect();

        self::assertStringContainsString('Too many failed login attempts', $crawler->filter('.alert-danger')->text());
    }

    public function testLoginThrottlingBlocksEvenValidCredentials(): void
    {
        // The limiter lives in the kernel's memory in tests (config/packages/test/cache.yaml).
        $this->client->disableReboot();
        for ($i = 0; $i < 5; ++$i) {
            $this->submitLoginForm(AppFixtures::TEST_USER_EMAIL, 'wrongpassword');
        }

        $this->submitLoginForm(AppFixtures::TEST_USER_EMAIL, AppFixtures::TEST_USER_PASSWORD);
        $crawler = $this->client->followRedirect();

        self::assertStringContainsString('Too many failed login attempts', $crawler->filter('.alert-danger')->text());
    }

    public function testAnotherSpellingOfTheLockedAddressIsLockedToo(): void
    {
        // The limiter lives in the kernel's memory in tests (config/packages/test/cache.yaml).
        $this->client->disableReboot();
        for ($i = 0; $i < 5; ++$i) {
            $this->submitLoginForm(AppFixtures::TEST_USER_EMAIL, 'wrongpassword');
        }

        // Logs in to the same account, so it must meet the same lock.
        $this->submitLoginForm('  '.AppFixtures::TEST_USER_EMAIL.' ', AppFixtures::TEST_USER_PASSWORD);
        $crawler = $this->client->followRedirect();

        self::assertStringContainsString('Too many failed login attempts', $crawler->filter('.alert-danger')->text());
    }

    private function submitLoginForm(string $email, string $password): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->form();
        $form['_username'] = $email;
        $form['_password'] = $password;
        $this->client->submit($form);
    }
}
