<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Contractor;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('invalidJsonBodies')]
    public function testCreateRejectsInvalidJsonBody(string $body): void
    {
        $crawler = $this->client->request('GET', '/');
        $csrfToken = $crawler->filter('meta[name="csrf-token-contractor-create"]')->attr('content');

        $this->client->request(
            'POST',
            '/contractor/create',
            [],
            [],
            ['HTTP_X-CSRF-Token' => $csrfToken, 'CONTENT_TYPE' => 'application/json'],
            $body,
        );

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type', 'application/json');
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidContractorData')]
    public function testCreateReturnsValidationErrorsWithoutSaving(array $payload, string $message): void
    {
        $countBefore = $this->em()->getRepository(Contractor::class)->count([]);
        $crawler = $this->client->request('GET', '/');
        $csrfToken = $crawler->filter('meta[name="csrf-token-contractor-create"]')->attr('content');

        $this->client->request(
            'POST',
            '/contractor/create',
            [],
            [],
            ['HTTP_X-CSRF-Token' => $csrfToken, 'CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type', 'application/json');
        $response = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($response);
        self::assertArrayHasKey('errors', $response);
        self::assertIsArray($response['errors']);
        self::assertContains($message, $response['errors']);
        self::assertSame($countBefore, $this->em()->getRepository(Contractor::class)->count([]));
    }

    #[DataProvider('validContractorNames')]
    public function testCreateSavesContractorAndReturnsJson(string $submittedName, string $expectedName): void
    {
        $crawler = $this->client->request('GET', '/');
        $csrfToken = $crawler->filter('meta[name="csrf-token-contractor-create"]')->attr('content');

        $this->client->request(
            'POST',
            '/contractor/create',
            [],
            [],
            ['HTTP_X-CSRF-Token' => $csrfToken, 'CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => $submittedName], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();

        $responseData = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($responseData);
        self::assertArrayHasKey('id', $responseData);
        self::assertSame($expectedName, $responseData['name']);

        $saved = $this->em()->find(Contractor::class, $responseData['id']);

        self::assertNotNull($saved);
        self::assertSame($expectedName, $saved->getName());
    }

    public function testSearchReturnsEmptyArrayForWhitespaceOnlyQuery(): void
    {
        $this->client->request('GET', '/contractor/search?q=%20%20%20');

        self::assertResponseIsSuccessful();
        self::assertSame('[]', $this->client->getResponse()->getContent());
    }

    #[DataProvider('duplicateNameWhitespace')]
    public function testCreateReturns422ForDuplicateName(string $whitespace): void
    {
        $name = 'Duplicate contractor '.uniqid();
        $contractor = new Contractor($name);
        $this->em()->persist($contractor);
        $this->em()->flush();
        $id = $contractor->getId();
        $countBefore = $this->em()->getRepository(Contractor::class)->count([]);
        $crawler = $this->client->request('GET', '/');
        $token = $crawler->filter('meta[name="csrf-token-contractor-create"]')->attr('content');
        self::assertNotNull($token);

        $this->postContractor($whitespace.$name.$whitespace, $token);

        $this->assertDuplicateResponse();
        self::assertSame($countBefore, $this->em()->getRepository(Contractor::class)->count([]));
        self::assertSame($id, $this->em()->getRepository(Contractor::class)->findOneBy(['name' => $name])?->getId());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidJsonBodies(): iterable
    {
        yield 'malformed JSON' => ['not-valid-json{{{'];
        yield 'string' => ['"just a string"'];
        yield 'null' => ['null'];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidContractorData(): iterable
    {
        $required = 'Please enter a contractor name.';
        yield 'missing name' => [[], $required];
        yield 'empty name' => [['name' => ''], $required];
        yield 'blank name' => [['name' => '   '], $required];
        yield 'null name' => [['name' => null], $required];
        yield 'too short' => [['name' => 'A'], 'The contractor name must be at least 2 characters long.'];
        yield 'too long' => [
            ['name' => str_repeat('ą', 256)],
            'The contractor name cannot be longer than 255 characters.',
        ];
        yield 'array name' => [['name' => ['ACME']], 'This value is not valid.'];
        yield 'extra field' => [
            ['name' => 'New contractor', 'unexpected' => true],
            'This form should not contain extra fields.',
        ];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validContractorNames(): iterable
    {
        yield 'ordinary name' => ['New contractor', 'New contractor'];
        yield 'trimmed name' => ['  Écolier  ', 'Écolier'];
        yield 'minimum length' => ['Ąę', 'Ąę'];
        yield 'maximum length' => [str_repeat('ą', 255), str_repeat('ą', 255)];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function duplicateNameWhitespace(): iterable
    {
        yield 'exact name' => [''];
        yield 'name with surrounding spaces' => ['  '];
    }

    private function postContractor(string $name, string $token): void
    {
        $this->client->request('POST', '/contractor/create', server: [
            'HTTP_X_CSRF_TOKEN' => $token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => $name], JSON_THROW_ON_ERROR));
    }

    private function assertDuplicateResponse(): void
    {
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame(
            ['errors' => ['The contractor already exists — select it from the list.']],
            json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR),
        );
    }
}
