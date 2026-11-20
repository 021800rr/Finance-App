<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\DataFixtures\AppFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The header fields every /api response gets, whoever built it (ApiResponseHeadersSubscriber).
 */
final class ApiResponseHeadersApiTest extends WebTestCase
{
    private const string ORIGIN = 'http://localhost:5173';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    /**
     * RFC 6749, section 5.1, for an OAuth token endpoint: no cache may store a response with tokens.
     */
    public function testTheTokensOfALoginAreStoredNowhere(): void
    {
        $this->client->jsonRequest('POST', '/api/login_check', [
            'email' => AppFixtures::TEST_USER_EMAIL,
            'password' => AppFixtures::TEST_USER_PASSWORD,
        ]);

        self::assertResponseIsSuccessful();
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertResponseHeaderSame('Pragma', 'no-cache');
    }

    /**
     * Fetch Standard, "CORS protocol and HTTP caches": an answer naming the request's origin varies with it, errors
     * too (the 404 of an unknown address, the 401 of a missing token).
     */
    public function testAnErrorForAnOriginVariesWithTheOrigin(): void
    {
        foreach (['/api/no-such-thing', '/api/accounts'] as $path) {
            $this->client->request('GET', $path, server: ['HTTP_ORIGIN' => self::ORIGIN]);

            self::assertResponseHeaderSame('Access-Control-Allow-Origin', self::ORIGIN, $path);
            self::assertContains('Origin', $this->client->getResponse()->getVary(), $path);
        }
    }

    /**
     * Not only API Platform's own responses: also the problems built outside it (ProblemResponse).
     */
    public function testEveryAnswerForbidsSniffingAndFraming(): void
    {
        foreach (['/api/no-such-thing', '/api/accounts', '/api/docs.jsonopenapi'] as $path) {
            $this->client->request('GET', $path);

            self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff', $path);
            self::assertResponseHeaderSame('X-Frame-Options', 'DENY', $path);
        }
    }
}
