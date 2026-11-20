<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\DataFixtures\AppFixtures;
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

        $this->assertProblem(405, 'http.method_not_allowed', 'Method Not Allowed. Allowed: POST, OPTIONS.');
        self::assertResponseHeaderSame('Allow', 'POST, OPTIONS');
    }

    /**
     * The Allow header of a 405 names every supported method (RFC 9110, section 15.5.6): HEAD, which the router serves
     * wherever it serves GET, and OPTIONS.
     */
    public function testAllowOfA405NamesHeadNextToGetAndOptions(): void
    {
        $this->client->request('DELETE', '/api/contractors/1');
        $this->assertProblem(405, 'http.method_not_allowed', 'Method Not Allowed. Allowed: GET, HEAD, OPTIONS.');
        self::assertResponseHeaderSame('Allow', 'GET, HEAD, OPTIONS');

        $this->client->request('PUT', '/api/accounts/1');
        $this->assertProblem(
            405,
            'http.method_not_allowed',
            'Method Not Allowed. Allowed: GET, HEAD, PATCH, OPTIONS.',
        );
        self::assertResponseHeaderSame('Allow', 'GET, HEAD, PATCH, OPTIONS');
    }

    /**
     * OPTIONS names the options of the resource (RFC 9110, section 9.3.7): Allow, and Accept-Patch where PATCH is
     * allowed (RFC 5789, section 3.1).
     */
    public function testOptionsNamesTheMethodsAndThePatchFormats(): void
    {
        $this->client->request('OPTIONS', '/api/accounts/1');
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $this->client->getResponse()->getContent());
        self::assertResponseHeaderSame('Allow', 'GET, HEAD, PATCH, OPTIONS');
        self::assertResponseHeaderSame('Accept-Patch', 'application/merge-patch+json');

        $this->client->request('OPTIONS', '/api/login_check');
        self::assertResponseStatusCodeSame(204);
        self::assertResponseHeaderSame('Allow', 'POST, OPTIONS');
        self::assertResponseNotHasHeader('Accept-Patch');
    }

    public function testOptionsOfAnUnknownPathIsNotFound(): void
    {
        $this->client->request('OPTIONS', '/api/no-such-thing');

        $this->assertProblem(404, 'http.not_found', 'Not Found.');
    }

    /**
     * A method the API does not know gets 501 (RFC 9110, section 9.1), whether or not the path exists; a known method
     * the resource does not support stays a 405 (above).
     */
    #[DataProvider('pathsForAnUnknownMethod')]
    public function testUnknownMethodIsNotImplemented(string $path): void
    {
        $this->client->request('FOO', $path);

        $this->assertProblem(501, 'http.not_implemented', 'Not Implemented. The API does not know the method FOO.');
        self::assertResponseNotHasHeader('Allow');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pathsForAnUnknownMethod(): iterable
    {
        yield 'resource' => ['/api/accounts/1'];
        yield 'login' => ['/api/login_check'];
        yield 'unknown path' => ['/api/no-such-thing'];
    }

    public function testUnknownApiPathIsNotFound(): void
    {
        $this->client->request('GET', '/api/no-such-thing');

        $this->assertProblem(404, 'http.not_found', 'Not Found.');
    }

    public function testValidLoginStillReturnsAToken(): void
    {
        $this->postLogin('application/json', json_encode([
            'email' => AppFixtures::TEST_USER_EMAIL,
            'password' => AppFixtures::TEST_USER_PASSWORD,
        ], JSON_THROW_ON_ERROR));

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
