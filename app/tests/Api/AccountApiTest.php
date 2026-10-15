<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Account;
use App\Tests\DatabaseTestTrait;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountApiTest extends WebTestCase
{
    use DatabaseTestTrait;

    private const VALID_EMAIL = 'test@example.com';
    private const VALID_PASSWORD = 'test1234';

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

    public function testGetAccountRequiresAuthentication(): void
    {
        $account = $this->firstAccount();

        $this->client->request('GET', '/api/accounts/' . $account->getId());

        self::assertResponseStatusCodeSame(401);
    }

    public function testAccountsCollectionIsNotExposed(): void
    {
        $token = $this->obtainJwtToken();

        $this->client->request('GET', '/api/accounts', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testGetSingleAccountWithValidTokenReturnsAccount(): void
    {
        $account = $this->firstAccount();
        $token = $this->obtainJwtToken();

        $this->client->request('GET', '/api/accounts/' . $account->getId(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($account->getAccountNumber(), (string) $this->client->getResponse()->getContent());
    }

    private function obtainJwtToken(): string
    {
        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => self::VALID_EMAIL, 'password' => self::VALID_PASSWORD], JSON_THROW_ON_ERROR),
        );

        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($data) || !isset($data['token']) || !is_string($data['token'])) {
            throw new RuntimeException('Could not obtain a JWT token in the test.');
        }

        return $data['token'];
    }

    private function firstAccount(): Account
    {
        $account = $this->em()->getRepository(Account::class)->findOneBy([]);

        if (null === $account) {
            throw new RuntimeException('Fixtures muszą dostarczyć co najmniej jedno konto.');
        }

        return $account;
    }
}
