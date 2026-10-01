<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Contractor;
use App\Tests\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContractorControllerTest extends WebTestCase
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

    public function testSearchReturnsEmptyArrayForBlankQuery(): void
    {
        $this->client->request('GET', '/contractor/search?q=');

        self::assertResponseIsSuccessful();
        self::assertSame('[]', $this->client->getResponse()->getContent());
    }

    public function testSearchReturnsMatchingContractors(): void
    {
        $uniquePart = uniqid();
        $contractor = new Contractor('Findable ' . $uniquePart);
        $this->em()->persist($contractor);
        $this->em()->flush();

        $this->client->request('GET', '/contractor/search?q=' . $uniquePart);

        self::assertResponseIsSuccessful();

        $responseData = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($responseData);
        self::assertCount(1, $responseData);

        $firstResult = $responseData[0];
        self::assertIsArray($firstResult);
        self::assertSame($contractor->getId(), $firstResult['id']);
        self::assertSame($contractor->getName(), $firstResult['name']);
    }

    public function testSearchReturnsEmptyArrayWhenNoMatchFound(): void
    {
        $this->client->request('GET', '/contractor/search?q=nonexistent-' . uniqid());

        self::assertResponseIsSuccessful();
        self::assertSame('[]', $this->client->getResponse()->getContent());
    }

    public function testCreateRejectsInvalidCsrfToken(): void
    {
        $this->client->request(
            'POST',
            '/contractor/create',
            [],
            [],
            ['HTTP_X-CSRF-Token' => 'invalid-token', 'CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['name' => 'Test'])
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testCreateRejectsInvalidJsonBody(): void
    {
        $crawler = $this->client->request('GET', '/');
        $csrfToken = $crawler->filter('meta[name="csrf-token-contractor-create"]')->attr('content');

        $this->client->request(
            'POST',
            '/contractor/create',
            [],
            [],
            ['HTTP_X-CSRF-Token' => $csrfToken, 'CONTENT_TYPE' => 'application/json'],
            'not-valid-json{{{'
        );

        self::assertResponseStatusCodeSame(400);
    }

    public function testCreateRejectsEmptyName(): void
    {
        $crawler = $this->client->request('GET', '/');
        $csrfToken = $crawler->filter('meta[name="csrf-token-contractor-create"]')->attr('content');

        $this->client->request(
            'POST',
            '/contractor/create',
            [],
            [],
            ['HTTP_X-CSRF-Token' => $csrfToken, 'CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['name' => '   '])
        );

        self::assertResponseStatusCodeSame(400);
    }

    public function testCreateSavesContractorAndReturnsJson(): void
    {
        $crawler = $this->client->request('GET', '/');
        $csrfToken = $crawler->filter('meta[name="csrf-token-contractor-create"]')->attr('content');

        $contractorName = 'Test Contractor ' . uniqid();

        $this->client->request(
            'POST',
            '/contractor/create',
            [],
            [],
            ['HTTP_X-CSRF-Token' => $csrfToken, 'CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['name' => $contractorName])
        );

        self::assertResponseIsSuccessful();

        $responseData = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($responseData);
        self::assertArrayHasKey('id', $responseData);
        self::assertSame($contractorName, $responseData['name']);

        $saved = $this->em()->find(Contractor::class, $responseData['id']);

        self::assertNotNull($saved);
        self::assertSame($contractorName, $saved->getName());
    }

    public function testSearchReturnsEmptyArrayForWhitespaceOnlyQuery(): void
    {
        $this->client->request('GET', '/contractor/search?q=%20%20%20');

        self::assertResponseIsSuccessful();
        self::assertSame('[]', $this->client->getResponse()->getContent());
    }
}
