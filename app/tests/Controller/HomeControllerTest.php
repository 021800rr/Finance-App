<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    use DatabaseTestTrait;
    use LogsInTrait;
    use TestDataTrait;

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

    public function testIndexRendersSuccessfully(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    public function testIndexHidesHiddenAccountsByDefault(): void
    {
        $account = $this->createAccount(hidden: true);

        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[action*="'.$account->getId().'/toggle-hidden"]');
    }

    public function testIndexShowsHiddenAccountsWhenRequested(): void
    {
        $account = $this->createAccount(hidden: true);

        $this->client->request('GET', '/?show_hidden=1');

        self::assertSelectorExists('form[action*="'.$account->getId().'/toggle-hidden"]');
    }

    public function testIndexDisplaysAccountBalance(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 15000);

        $crawler = $this->client->request('GET', '/');

        self::assertStringContainsString('150,00', $crawler->filter('table')->first()->text());
    }
}
