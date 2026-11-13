<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NavbarTest extends WebTestCase
{
    use DatabaseTestTrait;
    use LogsInTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->initEntityManager($this->client->getKernel());
        $this->logIn();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testUserMenuShowsIconInsteadOfEmailText(): void
    {
        $crawler = $this->client->request('GET', '/');

        $toggle = $crawler->filter('.navbar .dropdown-toggle');

        $toggle->text()
            |> trim(...)
            |> (fn ($x) => self::assertStringNotContainsString(AppFixtures::TEST_USER_EMAIL, $x));
        self::assertSelectorExists('.navbar .dropdown-toggle svg');
    }

    public function testUserMenuKeepsEmailAsAccessibleName(): void
    {
        $crawler = $this->client->request('GET', '/');

        $toggle = $crawler->filter('.navbar .dropdown-toggle');

        self::assertSame(AppFixtures::TEST_USER_EMAIL, $toggle->attr('aria-label'));
    }

    public function testUserMenuStillOffersLogout(): void
    {
        $this->client->request('GET', '/');

        self::assertSelectorExists('.navbar .dropdown-menu a[href="/logout"]');
    }
}
