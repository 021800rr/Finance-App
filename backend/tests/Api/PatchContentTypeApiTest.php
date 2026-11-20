<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Account;
use App\Tests\DatabaseTestTrait;
use App\Tests\JwtTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A PATCH refused for its Content-Type (415) says which ones a patch can have (RFC 5789, section 2.2).
 */
final class PatchContentTypeApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use JwtTrait;

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

    public function testAnUnsupportedPatchNamesThePatchFormats(): void
    {
        $account = $this->em()->getRepository(Account::class)->findOneBy([]);
        self::assertNotNull($account, 'The fixtures must provide an account.');

        $this->client->request(
            'PATCH',
            '/api/accounts/'.$account->getId(),
            server: $this->authHeaders() + ['CONTENT_TYPE' => 'application/json'],
            content: '{"hidden": true}',
        );

        self::assertResponseStatusCodeSame(415);
        self::assertResponseHeaderSame('Accept-Patch', 'application/merge-patch+json');
    }

    public function testAnUnsupportedPostNamesNoPatchFormats(): void
    {
        $this->client->request(
            'POST',
            '/api/contractors',
            server: $this->authHeaders() + ['CONTENT_TYPE' => 'text/plain'],
            content: '{"name": "A contractor"}',
        );

        self::assertResponseStatusCodeSame(415);
        self::assertResponseNotHasHeader('Accept-Patch');
    }
}
