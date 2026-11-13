<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\DataFixtures\AppFixtures;
use App\Tests\JwtTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * GET /api/me names the logged-in user, so a browser, which cannot read its HttpOnly cookies, learns whether it is
 * logged in.
 */
final class MeApiTest extends WebTestCase
{
    use JwtTrait;
    use ReadsJsonTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testNamesTheUserOfTheJwt(): void
    {
        $this->client->request('GET', '/api/me', server: $this->authHeaders());

        self::assertResponseIsSuccessful();
        $this->assertEmail(AppFixtures::TEST_USER_EMAIL);
    }

    public function testTheCookieAloneIsEnough(): void
    {
        // The BEARER cookie is Secure: the test client sends it only over HTTPS.
        $this->client->jsonRequest('POST', '/api/login_check', [
            'email' => AppFixtures::TEST_USER_EMAIL,
            'password' => AppFixtures::TEST_USER_PASSWORD,
        ], ['HTTPS' => 'on']);

        $this->client->request('GET', '/api/me', server: ['HTTPS' => 'on']);

        self::assertResponseIsSuccessful();
        $this->assertEmail(AppFixtures::TEST_USER_EMAIL);
    }

    public function testWithoutATokenItIsUnauthorized(): void
    {
        $this->client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
        $problem = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame('auth.token_missing', $problem['errorCode'] ?? null);
    }

    public function testOpenApiListsItWithTheLogin(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        self::assertResponseIsSuccessful();
        $docs = self::decodeObject((string) $this->client->getResponse()->getContent());

        $operation = self::objectAt($docs, 'paths', '/api/me', 'get');
        self::assertSame(['Login Check'], $operation['tags'] ?? null);
        self::assertArrayHasKey('401', self::objectAt($operation, 'responses'));
        self::assertArrayHasKey('email', self::objectAt($docs, 'components', 'schemas', 'Me', 'properties'));
    }

    private function assertEmail(string $email): void
    {
        $me = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame($email, self::stringAt($me, 'email'));
    }
}
