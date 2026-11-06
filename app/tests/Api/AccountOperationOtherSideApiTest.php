<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\DatabaseTestTrait;
use App\Tests\JwtTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * GET /api/account_operations/{id}/other-side finds the other operation of a transfer, as the web app's 🔗 link.
 */
final class AccountOperationOtherSideApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use JwtTrait;
    use ReadsJsonTrait;
    use TestDataTrait;

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

    public function testEachSideOfATransferLeadsToTheOther(): void
    {
        $uuid = Uuid::v4();
        $from = $this->createOperation($this->createAccount(), amount: -1000, transactionUuid: $uuid);
        $to = $this->createOperation($this->createAccount(), amount: 1000, transactionUuid: $uuid);

        foreach ([[$from, $to], [$to, $from]] as [$side, $otherSide]) {
            $this->requestOtherSide((int) $side->getId());

            self::assertResponseIsSuccessful();
            $found = self::decodeObject((string) $this->client->getResponse()->getContent());
            self::assertSame($otherSide->getId(), $found['id'] ?? null);
            self::assertSame($otherSide->getAccount()->getId(), $found['accountId'] ?? null);
            self::assertSame($uuid->toRfc4122(), $found['transactionUuid'] ?? null);
        }
    }

    public function testAnOperationOutsideATransferHasNoOtherSide(): void
    {
        $operation = $this->createOperation($this->createAccount());

        $this->requestOtherSide((int) $operation->getId());

        $this->assertNotFound('account_operation.other_side_not_found');
    }

    public function testATransferWhoseOtherSideWasDeletedHasNoOtherSide(): void
    {
        $operation = $this->createOperation($this->createAccount(), transactionUuid: Uuid::v4());

        $this->requestOtherSide((int) $operation->getId());

        $this->assertNotFound('account_operation.other_side_not_found');
    }

    public function testAMissingOperationIsNotFound(): void
    {
        $this->requestOtherSide(2_000_000_000);

        $this->assertNotFound('account_operation.not_found');
    }

    public function testOpenApiDocumentsBothNotFoundCodes(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        self::assertResponseIsSuccessful();
        $docs = self::decodeObject((string) $this->client->getResponse()->getContent());

        $notFound = self::stringAt($docs, 'paths', '/api/account_operations/{id}/other-side', 'get', 'responses', '404',
            'description');
        self::assertStringContainsString('account_operation.not_found', $notFound);
        self::assertStringContainsString('account_operation.other_side_not_found', $notFound);
    }

    private function requestOtherSide(int $id): void
    {
        $this->client->request('GET', "/api/account_operations/{$id}/other-side", server: [
            ...$this->authHeaders(),
            'HTTP_ACCEPT' => 'application/json',
        ]);
    }

    private function assertNotFound(string $errorCode): void
    {
        self::assertResponseStatusCodeSame(404);
        $problem = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame($errorCode, $problem['errorCode'] ?? null);
    }
}
