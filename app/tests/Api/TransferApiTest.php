<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testDescriptionLongerThan255CharactersIsRejectedWithoutCreatingOperations(): void
    {
        $countBefore = $this->em()->getRepository(AccountOperation::class)->count([]);

        $this->transfer($this->account('Bank1-ROR-PLN'), $this->account('Bank1-WAK-PLN'), str_repeat('a', 256));

        self::assertResponseStatusCodeSame(422);
        $violations = self::rowsAt(
            self::decodeObject((string) $this->client->getResponse()->getContent()),
            'violations',
        );
        $messages = array_column($violations, 'message', 'propertyPath');
        self::assertSame('The description cannot be longer than 255 characters.', $messages['description'] ?? null);
        self::assertSame($countBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    #[DataProvider('accountFields')]
    public function testHiddenAccountIsRejectedWithoutCreatingOperations(string $field): void
    {
        $accounts = [
            'fromAccountId' => $this->account('Bank1-ROR-PLN'),
            'toAccountId' => $this->account('Bank1-WAK-PLN'),
        ];
        $accounts[$field]->setHidden(true);
        $this->em()->flush();
        $countBefore = $this->em()->getRepository(AccountOperation::class)->count([]);

        $this->transfer($accounts['fromAccountId'], $accounts['toAccountId']);

        self::assertResponseStatusCodeSame(422);
        $violations = self::rowsAt(
            self::decodeObject((string) $this->client->getResponse()->getContent()),
            'violations',
        );
        self::assertSame(
            [$field => 'This account is hidden. Please select an account that is not hidden.'],
            array_column($violations, 'message', 'propertyPath'),
        );
        self::assertSame($countBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    #[DataProvider('accountFields')]
    public function testAccountIdAboveSqlIntegerIsNotFoundWithoutCreatingOperations(string $field): void
    {
        $ids = [
            'fromAccountId' => $this->account('Bank1-ROR-PLN')->getId(),
            'toAccountId' => $this->account('Bank1-WAK-PLN')->getId(),
        ];
        $ids[$field] = 2147483648;
        $countBefore = $this->em()->getRepository(AccountOperation::class)->count([]);

        $this->transferBetweenIds($ids['fromAccountId'], $ids['toAccountId']);

        self::assertResponseStatusCodeSame(404);
        self::assertSame($countBefore, $this->em()->getRepository(AccountOperation::class)->count([]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function accountFields(): iterable
    {
        yield 'from' => ['fromAccountId'];
        yield 'to' => ['toAccountId'];
    }

    public function testOpenApiDocumentsThatAHiddenAccountIsRejected(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        self::assertResponseIsSuccessful();

        $docs = self::decodeObject((string) $this->client->getResponse()->getContent());
        $create = self::objectAt($docs, 'paths', '/api/transfers', 'post');

        self::assertIsString($create['description'] ?? null);
        self::assertStringContainsString('hidden account cannot be chosen on either side', $create['description']);
        $unprocessable = self::objectAt($create, 'responses', '422');
        self::assertIsString($unprocessable['description'] ?? null);
        self::assertStringContainsString('points to a hidden account', $unprocessable['description']);
        self::assertStringContainsString('transfer.accounts_must_differ', $unprocessable['description']);
        self::assertStringContainsString(
            'transfer.amount_to_required_for_currency_exchange',
            $unprocessable['description'],
        );
        self::assertStringContainsString('http.unprocessable_content', $unprocessable['description']);
        self::assertSame(
            [
                ['$ref' => '#/components/schemas/ConstraintViolation.jsonld'],
                ['$ref' => '#/components/schemas/Error.jsonld'],
            ],
            self::objectAt($unprocessable, 'content', 'application/ld+json', 'schema')['anyOf'] ?? null,
        );
    }

    #[DataProvider('storedDescriptions')]
    public function testDescriptionIsStoredTrimmedAndBlankAsNull(string $sent, ?string $stored): void
    {
        $this->transfer($this->account('Bank1-ROR-PLN'), $this->account('Bank1-WAK-PLN'), $sent);

        self::assertResponseStatusCodeSame(201);
        $data = self::decodeObject((string) $this->client->getResponse()->getContent());
        $this->em()->clear();
        self::assertSame(
            $stored,
            $this->em()->find(AccountOperation::class, self::intAt($data, 'outOperationId'))?->getDescription(),
        );
        self::assertSame(
            $stored,
            $this->em()->find(AccountOperation::class, self::intAt($data, 'inOperationId'))?->getDescription(),
        );
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function storedDescriptions(): iterable
    {
        yield '255 characters with surrounding spaces' => ['  '.str_repeat('ą', 255).'  ', str_repeat('ą', 255)];
        yield 'only spaces' => ['   ', null];
    }

    private function transfer(Account $from, Account $to, ?string $description = null): void
    {
        $this->transferBetweenIds($from->getId(), $to->getId(), $description);
    }

    private function transferBetweenIds(?int $fromId, ?int $toId, ?string $description = null): void
    {
        $this->client->request('POST', '/api/transfers', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
            'CONTENT_TYPE' => 'application/ld+json',
        ], content: json_encode([
            'fromAccountId' => $fromId,
            'toAccountId' => $toId,
            'operationDate' => '2026-01-15',
            'amountFrom' => '100.00',
            'description' => $description,
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
