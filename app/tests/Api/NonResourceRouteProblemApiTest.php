<?php

declare(strict_types=1);

namespace App\Tests\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Errors of /api routes that API Platform does not handle (POST /api/login_check, unknown paths) use the same
 * Problem Details shape as the rest of the API instead of Symfony's generic error page.
 */
final class NonResourceRouteProblemApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    #[DataProvider('malformedLogins')]
    public function testMalformedLoginIsABadRequest(string $body, string $detail): void
    {
        $this->postLogin('application/json', $body);

        $this->assertProblem(400, 'http.bad_request', $detail);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedLogins(): iterable
    {
        yield 'invalid JSON' => ['{bad', 'Invalid JSON.'];
        yield 'empty body' => ['', 'Invalid JSON.'];
        yield 'no password' => ['{"email":"test@example.com"}', 'The key "password" must be provided.'];
        yield 'password not a string' => [
            '{"email":"test@example.com","password":1}',
            'The key "password" must be a string.',
        ];
    }

    public function testLoginWithoutJsonSaysWhatToSend(): void
    {
        $this->postLogin('application/x-www-form-urlencoded', 'email=test%40example.com&password=test1234');

        $problem = $this->assertProblem(415, 'http.unsupported_media_type');
        self::assertIsString($problem['detail'] ?? null);
        self::assertStringContainsString('Content-Type: application/json', $problem['detail']);
    }

    public function testLoginByGetIsNotAllowed(): void
    {
        $this->client->request('GET', '/api/login_check');

        $this->assertProblem(405, 'http.method_not_allowed', 'Method Not Allowed. Allowed: POST.');
        self::assertResponseHeaderSame('Allow', 'POST');
    }

    public function testUnknownApiPathIsNotFound(): void
    {
        $this->client->request('GET', '/api/no-such-thing');

        $this->assertProblem(404, 'http.not_found', 'Not Found.');
    }

    public function testValidLoginStillReturnsAToken(): void
    {
        $this->postLogin('application/json', '{"email":"test@example.com","password":"test1234"}');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"token"', (string) $this->client->getResponse()->getContent());
    }

    /**
     * @return array<mixed>
     */
    private function assertProblem(int $status, string $errorCode, ?string $detail = null): array
    {
        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        $problem = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($problem);
        self::assertSame($status, $problem['status'] ?? null);
        self::assertSame($errorCode, $problem['errorCode'] ?? null);
        self::assertSame('urn:finance-app:problem:'.$errorCode, $problem['type'] ?? null);
        self::assertSame([], $problem['params'] ?? null);
        self::assertArrayNotHasKey('trace', $problem);
        self::assertArrayNotHasKey('class', $problem);
        if (null !== $detail) {
            self::assertSame($detail, $problem['detail'] ?? null);
        }

        return $problem;
    }

    private function postLogin(string $contentType, string $body): void
    {
        $this->client->request('POST', '/api/login_check', server: ['CONTENT_TYPE' => $contentType], content: $body);
    }
}
