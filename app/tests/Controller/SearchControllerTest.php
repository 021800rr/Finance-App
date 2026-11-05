<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Service\Paginator;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SearchControllerTest extends WebTestCase
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

    public function testIndexRendersSuccessfullyWithoutQuery(): void
    {
        $this->client->request('GET', '/search');

        self::assertResponseIsSuccessful();
    }

    public function testIndexReturnsEmptyResultsForBlankQuery(): void
    {
        $this->client->request('GET', '/search?q=');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('table tbody [data-operation-id]');
    }

    public function testIndexFindsMatchingOperationByDescription(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 15000, description: 'Unique test description '.uniqid());

        $crawler = $this->client->request('GET', '/search?q=Unique+test+description');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('table tbody tr')->count());
    }

    public function testIndexReturnsNoResultsForNonMatchingQuery(): void
    {
        $this->createOperation($this->createAccount(), 15000, description: 'Something else');
        $query = 'nothing-'.uniqid();

        $this->client->request('GET', '/search', ['q' => $query]);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('table tbody [data-operation-id]');
        self::assertSelectorTextContains('table tbody', sprintf('No results found for "%s"', $query));
    }

    public function testIndexRespectsPageParameter(): void
    {
        $marker = 'paged-'.uniqid();
        $account = $this->createAccount();
        for ($i = 0; $i < Paginator::DEFAULT_PER_PAGE + 2; ++$i) {
            $this->createOperation($account, 15000, description: "{$marker} {$i}");
        }

        $this->client->request('GET', '/search', ['q' => $marker, 'page' => '2']);

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, 'table tbody [data-operation-id]');
        self::assertSelectorTextContains('.pagination', '2 / 2');
    }

    public function testLongNumericQueryFindsMatchingDescription(): void
    {
        $query = str_repeat('9', 33);
        $description = 'Reference '.$query;
        $this->createOperation($this->createAccount(), 15000, description: $description);

        $this->client->request('GET', '/search', ['q' => $query]);

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('table tbody', $description);
    }
}
