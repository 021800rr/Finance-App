<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TransferApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use ReadsJsonTrait;

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

    public function testSameAccountReturns422WithoutCreatingOperations(): void
    {
        $account = $this->account('Bank1-ROR-PLN');
        $countBefore = $this->em()->getRepository(AccountOperation::class)->count([]);

        $this->transfer($account, $account);

        self::assertResponseStatusCodeSame(422);
        $error = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame('Cannot transfer between the same account.', self::stringAt($error, 'detail'));
        self::assertSame($countBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    public function testDifferentAccountsCreateTwoLinkedOperations(): void
    {
        $from = $this->account('Bank1-ROR-PLN');
        $to = $this->account('Bank1-WAK-PLN');
        $countBefore = $this->em()->getRepository(AccountOperation::class)->count([]);

        $this->transfer($from, $to);

        self::assertResponseStatusCodeSame(201);
        $data = self::decodeObject((string) $this->client->getResponse()->getContent());
        $this->em()->clear();
        $out = $this->em()->find(AccountOperation::class, self::intAt($data, 'outOperationId'));
        $in = $this->em()->find(AccountOperation::class, self::intAt($data, 'inOperationId'));
        self::assertNotNull($out);
        self::assertNotNull($in);
        self::assertSame(-10000, $out->getAmount());
        self::assertSame(10000, $in->getAmount());
        self::assertSame($from->getId(), $out->getAccount()->getId());
        self::assertSame($to->getId(), $in->getAccount()->getId());
        $transactionUuid = self::stringAt($data, 'transactionUuid');
        self::assertSame($transactionUuid, $out->getTransactionUuid()?->toRfc4122());
        self::assertSame($transactionUuid, $in->getTransactionUuid()?->toRfc4122());
        self::assertSame($countBefore + 2, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    private function transfer(Account $from, Account $to): void
    {
        $this->client->request('POST', '/api/transfers', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'CONTENT_TYPE' => 'application/ld+json',
        ], content: json_encode([
            'fromAccountId' => $from->getId(),
            'toAccountId' => $to->getId(),
            'operationDate' => '2026-01-15',
            'amountFrom' => '100.00',
        ], JSON_THROW_ON_ERROR));
    }

    private function obtainJwtToken(): string
    {
        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'test@example.com', 'password' => 'test1234'], JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();

        return self::stringAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'token');
    }

    private function account(string $number): Account
    {
        $account = $this->em()->getRepository(Account::class)->findOneBy(['accountNumber' => $number]);
        self::assertNotNull($account);

        return $account;
    }
}
