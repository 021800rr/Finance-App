<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The login sets the JWT (15 minutes) and the refresh token (30 days) as HttpOnly cookies and keeps both in the
 * body for clients without cookies. A refresh token works once, and the JWT it replaces is blocked; logging out
 * deletes the refresh token, blocks the JWT and clears both cookies.
 *
 * The cookies are Secure, and the test client sends them only over HTTPS.
 */
final class TokenApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use ReadsJsonTrait;

    private const array HTTPS = ['HTTPS' => 'on'];

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

    public function testLoginSetsHttpOnlyCookiesAndKeepsTheTokensInTheBody(): void
    {
        $tokens = $this->logIn();

        $jwt = $this->responseCookie('BEARER');
        self::assertSame($tokens['token'], $jwt->getValue());
        self::assertSame('/api', $jwt->getPath());
        self::assertEqualsWithDelta(time() + 900, $jwt->getExpiresTime(), 5);
        $this->assertProtected($jwt);

        $refresh = $this->responseCookie('refresh_token');
        self::assertSame($tokens['refresh_token'], $refresh->getValue());
        self::assertSame('/api/token', $refresh->getPath());
        self::assertEqualsWithDelta(time() + 30 * 86400, $refresh->getExpiresTime(), 5);
        $this->assertProtected($refresh);
    }

    public function testTheCookieAloneAuthenticates(): void
    {
        $this->logIn();

        $this->client->request('GET', '/api/accounts', server: self::HTTPS);

        self::assertResponseIsSuccessful();
    }

    public function testRefreshByCookieReplacesBothTokensAndBlocksThePreviousJwt(): void
    {
        $old = $this->logIn();

        $this->client->request('POST', '/api/token/refresh', server: self::HTTPS);

        self::assertResponseIsSuccessful();
        $new = $this->tokensFromBody();
        self::assertNotSame($old['token'], $new['token']);
        self::assertNotSame($old['refresh_token'], $new['refresh_token']);
        self::assertSame($new['token'], $this->responseCookie('BEARER')->getValue());
        self::assertSame($new['refresh_token'], $this->responseCookie('refresh_token')->getValue());

        $this->client->getCookieJar()->clear();
        $this->getAccounts($old['token']);
        $this->assertProblem(401, 'auth.token_invalid');
        $this->getAccounts($new['token']);
        self::assertResponseIsSuccessful();
    }

    public function testUsedRefreshTokenIsRefusedAndEndsTheWholeLogin(): void
    {
        $first = $this->logIn();
        $this->refreshByBody($first['refresh_token']);
        self::assertResponseIsSuccessful();
        $second = $this->tokensFromBody();

        $this->refreshByBody($first['refresh_token']);
        $this->assertProblem(401, 'auth.refresh_token_invalid');

        // Someone else may have used it first, so the token that replaced it stops working, too.
        $this->refreshByBody($second['refresh_token']);
        $this->assertProblem(401, 'auth.refresh_token_invalid');
    }

    public function testStoredRefreshTokenIsAHashNotTheToken(): void
    {
        $tokens = $this->logIn();

        $stored = $this->em()->getConnection()->fetchFirstColumn('SELECT refresh_token FROM refresh_token');

        self::assertCount(1, $stored);
        self::assertNotContains($tokens['refresh_token'], $stored);
    }

    public function testRefreshWithoutATokenSaysWhatToSend(): void
    {
        $this->client->jsonRequest('POST', '/api/token/refresh', []);

        $this->assertProblem(401, 'auth.refresh_token_missing');
    }

    public function testUnknownRefreshTokenIsRefused(): void
    {
        $this->refreshByBody('no-such-token');

        $this->assertProblem(401, 'auth.refresh_token_invalid');
    }

    public function testLogoutByCookiesEndsTheLoginAndClearsBothCookies(): void
    {
        $tokens = $this->logIn();

        $this->client->request('POST', '/api/token/invalidate', server: self::HTTPS);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', (string) $this->client->getResponse()->getContent());
        foreach (['BEARER' => '/api', 'refresh_token' => '/api/token'] as $name => $path) {
            $cleared = $this->responseCookie($name);
            self::assertTrue($cleared->isCleared(), $name);
            self::assertSame($path, $cleared->getPath(), $name);
            $this->assertProtected($cleared);
        }
        $this->assertLoginEnded($tokens);
    }

    public function testLogoutWithoutCookiesTakesTheTokensFromTheRequest(): void
    {
        $tokens = $this->logIn();
        $this->client->getCookieJar()->clear();

        $this->client->jsonRequest(
            'POST',
            '/api/token/invalidate',
            ['refresh_token' => $tokens['refresh_token']],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$tokens['token']],
        );

        self::assertResponseStatusCodeSame(204);
        $this->assertLoginEnded($tokens);
    }

    public function testLogoutWithoutARefreshTokenIsABadRequest(): void
    {
        $this->client->jsonRequest('POST', '/api/token/invalidate', []);

        $this->assertProblem(400, 'auth.refresh_token_missing');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function tokenAddresses(): iterable
    {
        yield 'refresh' => ['/api/token/refresh'];
        yield 'logout' => ['/api/token/invalidate'];
    }

    #[DataProvider('tokenAddresses')]
    public function testTokenAddressesAnswerOnlyPost(string $path): void
    {
        $this->client->request('GET', $path);

        $this->assertProblem(405, 'http.method_not_allowed');
        self::assertResponseHeaderSame('Allow', 'POST');
    }

    /**
     * The refresh bundle documents its route name as the path; the API docs show the address instead.
     */
    public function testOpenApiDocumentsTheTokenAddresses(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        self::assertResponseIsSuccessful();
        $paths = self::objectAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'paths');

        foreach (array_keys($paths) as $path) {
            self::assertStringStartsWith('/api/', $path);
        }
        foreach (['/api/token/refresh', '/api/token/invalidate'] as $path) {
            $operation = self::objectAt($paths, $path, 'post');
            self::assertSame(['Login Check'], $operation['tags'] ?? null, $path);
            // A browser sends no body, the refresh_token cookie carries the token.
            self::assertFalse(self::objectAt($operation, 'requestBody')['required'] ?? false, $path);
        }
        self::assertArrayHasKey('204', self::objectAt($paths, '/api/token/invalidate', 'post', 'responses'));
    }

    public function testWebLogoutStillRedirectsToTheLoginPage(): void
    {
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => AppFixtures::TEST_USER_EMAIL]);
        self::assertNotNull($user);
        $this->client->loginUser($user);

        $this->client->request('GET', '/logout');

        self::assertResponseRedirects('/login');
    }

    /**
     * @return array{token: string, refresh_token: string}
     */
    private function logIn(): array
    {
        $this->client->jsonRequest('POST', '/api/login_check', [
            'email' => AppFixtures::TEST_USER_EMAIL,
            'password' => AppFixtures::TEST_USER_PASSWORD,
        ], self::HTTPS);
        self::assertResponseIsSuccessful();

        return $this->tokensFromBody();
    }

    /**
     * @return array{token: string, refresh_token: string}
     */
    private function tokensFromBody(): array
    {
        $body = self::decodeObject((string) $this->client->getResponse()->getContent());

        return ['token' => self::stringAt($body, 'token'), 'refresh_token' => self::stringAt($body, 'refresh_token')];
    }

    private function refreshByBody(string $refreshToken): void
    {
        $this->client->jsonRequest('POST', '/api/token/refresh', ['refresh_token' => $refreshToken]);
    }

    private function getAccounts(string $jwt): void
    {
        $this->client->request('GET', '/api/accounts', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$jwt]);
    }

    /**
     * @param array{token: string, refresh_token: string} $tokens
     */
    private function assertLoginEnded(array $tokens): void
    {
        $this->client->getCookieJar()->clear();
        $this->getAccounts($tokens['token']);
        $this->assertProblem(401, 'auth.token_invalid');
        $this->refreshByBody($tokens['refresh_token']);
        $this->assertProblem(401, 'auth.refresh_token_invalid');
    }

    private function responseCookie(string $name): Cookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($name === $cookie->getName()) {
                return $cookie;
            }
        }

        self::fail(sprintf('The response sets no "%s" cookie.', $name));
    }

    private function assertProtected(Cookie $cookie): void
    {
        self::assertTrue($cookie->isHttpOnly(), $cookie->getName());
        self::assertTrue($cookie->isSecure(), $cookie->getName());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite(), $cookie->getName());
    }

    private function assertProblem(int $status, string $errorCode): void
    {
        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        $problem = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame($status, $problem['status'] ?? null);
        self::assertSame($errorCode, $problem['errorCode'] ?? null);
        self::assertNotSame('', self::stringAt($problem, 'detail'));
    }
}
