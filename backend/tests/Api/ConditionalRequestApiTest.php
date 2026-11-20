<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\DataFixtures\AppFixtures;
use App\Entity\Account;
use App\Tests\DatabaseTestTrait;
use App\Tests\JwtTrait;
use App\Tests\TestDataTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * If-Match and If-None-Match (RFC 9110, sections 13.1.1, 13.1.2 and 13.2.2): a failed If-Match gets 412 Precondition
 * Failed whatever the method; a matching If-None-Match gets 304 Not Modified on GET and HEAD and 412 on any other
 * method. A refused request changes nothing.
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

    #[DataProvider('safeMethods')]
    public function testAReadWhoseIfMatchFailsIsRefused(string $method): void
    {
        $etag = $this->etagOf($this->accountPath());

        // Section 8.8.3.2: If-Match compares strongly, so a weak tag never matches.
        foreach (['"other"', 'W/'.$etag] as $condition) {
            $this->client->request($method, $this->accountPath(), server: $this->ifMatch($condition, self::JSON));

            self::assertResponseStatusCodeSame(412, $condition);
        }
        foreach ([$etag, '"other", '.$etag, '*'] as $condition) {
            $this->client->request($method, $this->accountPath(), server: $this->ifMatch($condition, self::JSON));

            self::assertResponseStatusCodeSame(200, $condition);
        }
    }

    public function testAChangeOfAnotherRepresentationThanTheClientsIsRefusedAndNotMade(): void
    {
        $etag = $this->etagOf($this->accountPath());

        foreach (['"other"', 'W/'.$etag] as $condition) {
            $this->client->request(
                'PATCH',
                $this->accountPath(),
                server: $this->ifMatch($condition, self::MERGE_PATCH),
                content: '{"hidden": true}',
            );

            $this->assertPreconditionFailed($condition);
        }
        self::assertSame($etag, $this->etagOf($this->accountPath()));
    }

    public function testAChangeOfTheClientsRepresentationIsMade(): void
    {
        $this->client->request(
            'PATCH',
            $this->accountPath(),
            server: $this->ifMatch($this->etagOf($this->accountPath()), self::MERGE_PATCH),
            content: '{"hidden": true}',
        );

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($this->decodedResponse()['hidden'] ?? null);
    }

    /**
     * Section 13.2.2: If-Match is evaluated first; when it holds, If-None-Match still decides.
     */
    public function testBothConditionsMustHold(): void
    {
        $etag = $this->etagOf($this->accountPath());

        $this->client->request(
            'PATCH',
            $this->accountPath(),
            server: $this->ifMatch($etag, ['HTTP_IF_NONE_MATCH' => $etag] + self::MERGE_PATCH),
            content: '{"hidden": true}',
        );

        $this->assertPreconditionFailed('If-Match and If-None-Match');
        self::assertStringStartsWith('If-None-Match', $this->detail());
    }

    public function testADeleteWhoseIfMatchFailsIsRefusedAndNotMade(): void
    {
        $account = $this->em()->getRepository(Account::class)->findOneBy([]);
        self::assertNotNull($account, 'The fixtures must provide an account.');
        $path = '/api/account_operations/'.$this->createOperation($account)->getId();

        $this->client->request('DELETE', $path, server: $this->ifMatch('"other"', self::JSON));

        $this->assertPreconditionFailed('"other"');
        self::assertStringStartsWith('If-Match', $this->detail());
        $this->client->request('GET', $path, server: $this->authHeaders() + self::JSON);
        self::assertResponseStatusCodeSame(200);
    }

    /**
     * Section 13.1.1: "*" holds only where there is a current representation; a transfer, the login and the token
     * addresses have none, so If-Match always fails there.
     *
     * @return iterable<string, array{string}>
     */
    public static function addressesWithoutARepresentation(): iterable
    {
        yield 'transfer (API Platform)' => ['/api/transfers'];
        yield 'login' => ['/api/login_check'];
        yield 'refresh' => ['/api/token/refresh'];
        yield 'logout' => ['/api/token/invalidate'];
    }

    #[DataProvider('addressesWithoutARepresentation')]
    public function testIfMatchFailsWhereNothingIsRepresented(string $path): void
    {
        $this->client->request(
            'POST',
            $path,
            server: $this->ifMatch('*', ['CONTENT_TYPE' => 'application/json'] + self::JSON),
            content: '{}',
        );

        $this->assertPreconditionFailed($path);
    }

    public function testIfNoneMatchHoldsWhereNothingIsRepresented(): void
    {
        $this->client->jsonRequest('POST', '/api/login_check', [
            'email' => AppFixtures::TEST_USER_EMAIL,
            'password' => AppFixtures::TEST_USER_PASSWORD,
        ], ['HTTP_IF_NONE_MATCH' => '*']);

        self::assertResponseStatusCodeSame(200);
    }

    /**
     * Section 13.2.1: a request that fails anyway ignores the condition.
     */
    public function testAMissingRecordStaysNotFound(): void
    {
        $this->client->request('GET', '/api/accounts/999999', server: $this->conditional('*', self::JSON));
        self::assertResponseStatusCodeSame(404);

        $this->client->request(
            'PATCH',
            '/api/accounts/999999',
            server: $this->ifMatch('"other"', self::MERGE_PATCH),
            content: '{"hidden": true}',
        );
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

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    private function ifMatch(string $ifMatch, array $headers): array
    {
        return $this->authHeaders() + ['HTTP_IF_MATCH' => $ifMatch] + $headers;
    }

    private function assertPreconditionFailed(string $condition): void
    {
        self::assertResponseStatusCodeSame(412, $condition);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame(
            'urn:finance-app:problem:http.precondition_failed',
            $this->decodedResponse()['type'] ?? null,
            $condition,
        );
    }

    private function detail(): string
    {
        $detail = $this->decodedResponse()['detail'] ?? null;
        self::assertIsString($detail);

        return $detail;
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
