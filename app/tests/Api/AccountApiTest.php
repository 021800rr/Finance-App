<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Account;
use App\Tests\DatabaseTestTrait;
use App\Tests\JwtTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountApiTest extends WebTestCase
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

    public function testGetAccountRequiresAuthentication(): void
    {
        $account = $this->firstAccount();

        $this->client->request('GET', '/api/accounts/'.$account->getId());

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetAccountsCollectionRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/accounts');

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetAccountsCollectionWithValidTokenReturnsAllAccounts(): void
    {
        $token = $this->jwt();

        $this->client->request('GET', '/api/accounts', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame($this->em()->getRepository(Account::class)->count([]), $data['totalItems']);
        self::assertStringContainsString(
            $this->firstAccount()->getAccountNumber(),
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testGetSingleAccountWithValidTokenReturnsAccount(): void
    {
        $account = $this->firstAccount();
        $token = $this->jwt();

        $this->client->request('GET', '/api/accounts/'.$account->getId(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            $account->getAccountNumber(),
            (string) $this->client->getResponse()->getContent(),
        );
    }

    private function firstAccount(): Account
    {
        $account = $this->em()->getRepository(Account::class)->findOneBy([]);

        if (null === $account) {
            throw new \RuntimeException('Fixtures must provide at least one account.');
        }

        return $account;
    }
}
