<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\DatabaseTestTrait;
use App\Tests\JwtTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountBalanceApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use ReadsJsonTrait;
    use TestDataTrait;
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

    public function testBalanceShowsBothSaldosWhenFutureOperationsExist(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 100000, new \DateTimeImmutable('-3 days'));
        $this->createOperation($account, -5050, new \DateTimeImmutable('today'));
        $this->createOperation($account, 3000, new \DateTimeImmutable('+4 days'));

        $data = $this->getBalance((string) $account->getId());

        self::assertSame(['amount' => '979.50', 'currency' => 'PLN'], $data['saldo']);
        self::assertSame(['amount' => '949.50', 'currency' => 'PLN'], $data['saldoToday']);
        self::assertTrue($data['hasFutureOperations']);
        self::assertSame($account->getId(), $data['id']);
        self::assertSame($account->getAccountNumber(), $data['accountNumber']);
    }

    public function testBalanceHasNoFutureOperationsWhenAllOperationsArePastOrToday(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 12345, new \DateTimeImmutable('-1 day'));

        $data = $this->getBalance((string) $account->getId());

        self::assertSame($data['saldo'], $data['saldoToday']);
        self::assertFalse($data['hasFutureOperations']);
    }

    public function testBalanceOfAccountWithoutOperationsIsZero(): void
    {
        $data = $this->getBalance((string) $this->createAccount()->getId());

        self::assertSame(['amount' => '0.00', 'currency' => 'PLN'], $data['saldo']);
        self::assertFalse($data['hasFutureOperations']);
    }

    public function testPatchResponseDoesNotCarryBalances(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 1000, new \DateTimeImmutable('today'));

        $this->client->request(
            'PATCH',
            '/api/accounts/'.$account->getId(),
            server: $this->authHeaders() + [
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_ACCEPT' => 'application/ld+json',
            ],
            content: json_encode(['hidden' => true], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $data = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertTrue($data['hidden']);
        self::assertArrayNotHasKey('saldo', $data);
        self::assertArrayNotHasKey('saldoToday', $data);
    }

    public function testBalanceFieldsInPatchBodyAreIgnored(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 1000, new \DateTimeImmutable('today'));

        $this->client->request(
            'PATCH',
            '/api/accounts/'.$account->getId(),
            server: $this->authHeaders() + [
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_ACCEPT' => 'application/ld+json',
            ],
            content: json_encode(
                ['hidden' => false, 'saldo' => ['amount' => '999999.00', 'currency' => 'PLN']],
                JSON_THROW_ON_ERROR,
            ),
        );
        self::assertResponseIsSuccessful();

        $data = $this->getBalance((string) $account->getId());
        self::assertSame(['amount' => '10.00', 'currency' => 'PLN'], $data['saldo']);
    }

    public function testOpenApiMarksTheBalanceFieldsReadOnlyInThePatchRequestSchema(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        $document = self::decodeObject((string) $this->client->getResponse()->getContent());
        $properties = self::objectAt($document, 'components', 'schemas', 'Account.jsonMergePatch', 'properties');

        foreach (['saldo', 'saldoToday', 'hasFutureOperations'] as $field) {
            self::assertTrue(
                self::objectAt($properties, $field)['readOnly'] ?? false,
                "{$field} must be read-only in the PATCH request schema.",
            );
        }
        self::assertArrayNotHasKey('readOnly', self::objectAt($properties, 'hidden'));
        self::assertArrayNotHasKey('readOnly', self::objectAt($properties, 'isMain'));
        $saldo = self::objectAt($properties, 'saldo');
        self::assertNotEmpty($saldo['description'] ?? null);
        self::assertSame('PLN', self::objectAt($saldo, 'example')['currency'] ?? null);
    }

    public function testUnknownAccountReturns404(): void
    {
        $this->client->request(
            'GET',
            '/api/accounts/2147483647',
            server: $this->authHeaders() + ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testAccountIdAboveInt4MaxReturns404(): void
    {
        $this->client->request(
            'GET',
            '/api/accounts/'.PHP_INT_MAX,
            server: $this->authHeaders() + ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testAccountRequiresAuthentication(): void
    {
        $account = $this->createAccount();

        $this->client->request(
            'GET',
            '/api/accounts/'.$account->getId(),
            server: ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return array<string, mixed>
     */
    private function getBalance(string $accountId): array
    {
        $this->client->request(
            'GET',
            '/api/accounts/'.$accountId,
            server: $this->authHeaders() + ['HTTP_ACCEPT' => 'application/ld+json'],
        );
        self::assertResponseIsSuccessful();

        return self::decodeObject((string) $this->client->getResponse()->getContent());
    }
}
