<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Account;
use App\Tests\DatabaseTestTrait;
use App\Tests\JwtTrait;
use App\Tests\TestDataTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * If-None-Match (RFC 9110, section 13.1.2): a GET or HEAD of an unchanged representation gets 304 Not Modified,
 * any other method gets 412 Precondition Failed and changes nothing.
 */
final class ConditionalRequestApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use JwtTrait;
    use TestDataTrait;

    private const array JSON = ['HTTP_ACCEPT' => 'application/json'];

    private const array MERGE_PATCH = ['CONTENT_TYPE' => 'application/merge-patch+json'] + self::JSON;

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

    /**
     * @return iterable<string, array{string}>
     */
    public static function safeMethods(): iterable
    {
        yield 'GET' => ['GET'];
        yield 'HEAD' => ['HEAD'];
    }

    #[DataProvider('safeMethods')]
    public function testAnUnchangedRepresentationIsNotModified(string $method): void
    {
        $etag = $this->etagOf($this->accountPath());

        foreach ([$etag, 'W/'.$etag, '"other", '.$etag, '*'] as $condition) {
            $this->client->request($method, $this->accountPath(), server: $this->conditional($condition, self::JSON));

            self::assertResponseStatusCodeSame(304, $condition);
            self::assertSame('', $this->client->getResponse()->getContent(), $condition);
            self::assertResponseHeaderSame('ETag', $etag, $condition);
        }
    }

    #[DataProvider('safeMethods')]
    public function testAChangedRepresentationIsSentWhole(string $method): void
    {
        $this->client->request($method, $this->accountPath(), server: $this->conditional('"other"', self::JSON));

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHasHeader('ETag');
    }

    /**
     * RFC 9110, section 9.3.2: HEAD sends the header fields of GET, the ETag and Vary among them, and nothing more.
     */
    public function testHeadHasTheHeaderFieldsOfGet(): void
    {
        $this->client->request('GET', $this->accountPath(), server: $this->authHeaders() + self::JSON);
        $get = $this->headerFields();

        $this->client->request('HEAD', $this->accountPath(), server: $this->authHeaders() + self::JSON);

        self::assertResponseStatusCodeSame(200);
        self::assertArrayHasKey('etag', $get);
        self::assertSame($get, $this->headerFields());
    }

    /**
     * Section 13.2.1: a request that fails anyway ignores the condition.
     */
    public function testAMissingRecordStaysNotFound(): void
    {
        $this->client->request('GET', '/api/accounts/999999', server: $this->conditional('*', self::JSON));

        self::assertResponseStatusCodeSame(404);
    }

    public function testAChangeToAnUnchangedRepresentationIsRefusedAndNotMade(): void
    {
        $etag = $this->etagOf($this->accountPath());

        foreach ([$etag, '*'] as $condition) {
            $this->client->request(
                'PATCH',
                $this->accountPath(),
                server: $this->conditional($condition, self::MERGE_PATCH),
                content: '{"hidden": true}',
            );

            $this->assertPreconditionFailed($condition);
        }
        self::assertSame($etag, $this->etagOf($this->accountPath()));
    }

    public function testAChangeToAnotherRepresentationIsMade(): void
    {
        $this->client->request(
            'PATCH',
            $this->accountPath(),
            server: $this->conditional('"other"', self::MERGE_PATCH),
            content: '{"hidden": true}',
        );

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($this->decodedResponse()['hidden'] ?? null);
    }

    public function testADeleteOfAnExistingRepresentationIsRefusedAndNotMade(): void
    {
        $account = $this->em()->getRepository(Account::class)->findOneBy([]);
        self::assertNotNull($account, 'The fixtures must provide an account.');
        $path = '/api/account_operations/'.$this->createOperation($account)->getId();

        $this->client->request('DELETE', $path, server: $this->conditional('*', self::JSON));

        $this->assertPreconditionFailed('*');
        $this->client->request('GET', $path, server: $this->authHeaders() + self::JSON);
        self::assertResponseStatusCodeSame(200);
    }

    /**
     * Without a search name the contractor list is no representation (422), so "*" matches nothing.
     */
    public function testACreationWhereNothingIsRepresentedIsMade(): void
    {
        $this->client->request(
            'POST',
            '/api/contractors',
            server: $this->conditional('*', ['CONTENT_TYPE' => 'application/json'] + self::JSON),
            content: '{"name": "Conditional request contractor"}',
        );

        self::assertResponseStatusCodeSame(201);
    }

    /**
     * Section 13.2.1: an unsupported Content-Type is found before the content is processed, so its 415 comes first.
     */
    public function testAnUnsupportedContentTypeStaysUnsupported(): void
    {
        $this->client->request(
            'PATCH',
            $this->accountPath(),
            server: $this->conditional('*', ['CONTENT_TYPE' => 'text/plain'] + self::JSON),
            content: '{"hidden": true}',
        );

        self::assertResponseStatusCodeSame(415);
    }

    /**
     * The response's header fields but those that differ between any two responses.
     *
     * @return array<string, list<string|null>>
     */
    private function headerFields(): array
    {
        $headers = $this->client->getResponse()->headers->all();
        unset($headers['date'], $headers['x-debug-token'], $headers['x-debug-token-link']);
        ksort($headers);

        return $headers;
    }

    private function accountPath(): string
    {
        $account = $this->em()->getRepository(Account::class)->findOneBy([]);
        self::assertNotNull($account, 'The fixtures must provide an account.');

        return '/api/accounts/'.$account->getId();
    }

    private function etagOf(string $path): string
    {
        $this->client->request('GET', $path, server: $this->authHeaders() + self::JSON);
        self::assertResponseStatusCodeSame(200);
        $etag = $this->client->getResponse()->getEtag();
        self::assertNotNull($etag);

        return $etag;
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    private function conditional(string $ifNoneMatch, array $headers): array
    {
        return $this->authHeaders() + ['HTTP_IF_NONE_MATCH' => $ifNoneMatch] + $headers;
    }

    private function assertPreconditionFailed(string $condition): void
    {
        self::assertResponseStatusCodeSame(412, $condition);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('http.precondition_failed', $this->decodedResponse()['errorCode'] ?? null, $condition);
    }

    /**
     * @return array<mixed>
     */
    private function decodedResponse(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
