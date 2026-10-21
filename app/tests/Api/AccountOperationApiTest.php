<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\Household;
use App\Service\Paginator;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountOperationApiTest extends WebTestCase
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

    public function testQueryParameterFiltersTheCollection(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $this->createOperation($account, "Findable {$marker}");
        $this->createOperation($account, "Other {$marker}");

        $descriptions = $this->collectionDescriptions('/api/account_operations?q='.rawurlencode("Findable {$marker}"));

        self::assertSame(["Findable {$marker}"], $descriptions);
    }

    public function testQueryParameterTreatsPercentAsLiteral(): void
    {
        $marker = uniqid();
        $account = $this->createAccount();
        $this->createOperation($account, "Discount 100% {$marker}");
        $this->createOperation($account, "Discount 1000 {$marker}");

        $descriptions = $this->collectionDescriptions('/api/account_operations?q='.rawurlencode('100%'));

        self::assertContains("Discount 100% {$marker}", $descriptions);
        self::assertNotContains("Discount 1000 {$marker}", $descriptions);
    }

    public function testAccountOperationsRouteListsOnlyThatAccountsOperations(): void
    {
        $marker = uniqid();
        $accountA = $this->createAccount();
        $accountB = $this->createAccount();
        $this->createOperation($accountA, "In A {$marker}");
        $this->createOperation($accountB, "In B {$marker}");

        $descriptions = $this->collectionDescriptions('/api/accounts/'.$accountA->getId().'/operations');

        self::assertSame(["In A {$marker}"], $descriptions);
    }

    public function testAccountOperationsRoutePaginatesWithTotalItemsOfThatAccount(): void
    {
        $account = $this->createAccount();
        $other = $this->createAccount();
        for ($i = 0; $i < Paginator::DEFAULT_PER_PAGE + 2; ++$i) {
            $this->createOperation($account, "Paged {$i}");
        }
        $this->createOperation($other, 'Not counted');

        $data = $this->collection('/api/accounts/'.$account->getId().'/operations?page=2');

        self::assertSame(Paginator::DEFAULT_PER_PAGE + 2, $data['totalItems']);
        self::assertCount(2, self::rowsAt($data, 'member'));
        self::assertStringEndsWith('/operations?page=1', self::stringAt($data, 'view', 'previous'));
    }

    public function testSearchCollectionDescribesTheQueryParameterInTheHydraSearchTemplate(): void
    {
        $data = $this->collection('/api/account_operations?q=anything');

        self::assertStringEndsWith('{?q}', self::stringAt($data, 'search', 'template'));
    }

    public function testAccountOperationsRouteOfUnknownAccountReturns404(): void
    {
        $this->client->request('GET', '/api/accounts/2147483647/operations', server: $this->authHeaders());

        self::assertResponseStatusCodeSame(404);
    }

    public function testAccountOperationsRouteWithNonNumericAccountIdReturns404(): void
    {
        $this->client->request('GET', '/api/accounts/abc/operations', server: $this->authHeaders());

        self::assertResponseStatusCodeSame(404);
    }

    public function testAccountOperationsRouteWithAccountIdAboveInt4MaxReturns404(): void
    {
        $this->client->request('GET', '/api/accounts/'.PHP_INT_MAX.'/operations', server: $this->authHeaders());

        self::assertResponseStatusCodeSame(404);
    }

    public function testAccountOperationsRouteRequiresAuthentication(): void
    {
        $account = $this->createAccount();

        $this->client->request('GET', '/api/accounts/'.$account->getId().'/operations');

        self::assertResponseStatusCodeSame(401);
    }

    public function testSearchWithoutQueryParameterIsRejected(): void
    {
        $this->client->request('GET', '/api/account_operations', server: $this->authHeaders());

        self::assertResponseStatusCodeSame(422);
    }

    public function testSearchWithBlankQueryParameterIsRejected(): void
    {
        $this->client->request('GET', '/api/account_operations?q=%20%20%20', server: $this->authHeaders());

        self::assertResponseStatusCodeSame(422);
    }

    public function testSearchSpansAllAccounts(): void
    {
        $marker = uniqid();
        $this->createOperation($this->createAccount(), "In A {$marker}");
        $this->createOperation($this->createAccount(), "In B {$marker}");

        $descriptions = $this->collectionDescriptions('/api/account_operations?q='.rawurlencode($marker));

        self::assertEqualsCanonicalizing(["In A {$marker}", "In B {$marker}"], $descriptions);
    }

    public function testQueryParameterShorterThanTwoCharactersIsRejected(): void
    {
        $this->client->request('GET', '/api/account_operations?q=a', server: $this->authHeaders());

        self::assertResponseStatusCodeSame(422);
    }

    public function testOpenApiDocumentsTheAccountOperationsRoute(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        self::assertResponseIsSuccessful();

        $docs = self::decodeObject((string) $this->client->getResponse()->getContent());
        $operation = self::objectAt($docs, 'paths', '/api/accounts/{accountId}/operations', 'get');
        $parameters = array_column(self::rowsAt($operation, 'parameters'), 'in', 'name');

        self::assertSame(['accountId' => 'path', 'page' => 'query'], $parameters);
        self::assertArrayHasKey('404', self::objectAt($operation, 'responses'));
    }

    public function testAbsurdPageNumberReturnsAnEmptyPageInsteadOfFailing(): void
    {
        $descriptions = $this->collectionDescriptions('/api/account_operations?q=anything&page='.PHP_INT_MAX);

        self::assertSame([], $descriptions);
    }

    public function testPatchIgnoresAnAccountIdAndKeepsTheOperationOnItsAccount(): void
    {
        $account = $this->createAccount();
        $other = $this->createAccount();
        $operation = $this->createOperation($account, 'Stays put '.uniqid());

        $this->client->request(
            'PATCH',
            '/api/account_operations/'.$operation->getId(),
            server: $this->authHeaders() + ['CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['accountId' => $other->getId(), 'description' => 'Edited'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $this->em()->clear();
        $reloaded = $this->em()->find(AccountOperation::class, $operation->getId());
        self::assertNotNull($reloaded);
        self::assertSame($account->getId(), $reloaded->getAccount()->getId());
        self::assertSame('Edited', $reloaded->getDescription());
    }

    public function testOpenApiOffersTheAccountIdOnCreateButNotOnUpdate(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        self::assertResponseIsSuccessful();
        $docs = self::decodeObject((string) $this->client->getResponse()->getContent());

        $createProperties = $this->requestSchemaProperties($docs, '/api/account_operations', 'post');
        $updateProperties = $this->requestSchemaProperties($docs, '/api/account_operations/{id}', 'patch');

        self::assertArrayHasKey('accountId', $createProperties);
        self::assertArrayNotHasKey('accountId', $updateProperties);
        self::assertArrayHasKey('amount', $updateProperties);
    }

    public function testPatchWithTheSameAccountIdUpdatesTheOperation(): void
    {
        $account = $this->createAccount();
        $operation = $this->createOperation($account, 'Before '.uniqid());

        $this->client->request(
            'PATCH',
            '/api/account_operations/'.$operation->getId(),
            server: $this->authHeaders() + ['CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['accountId' => $account->getId(), 'description' => 'After'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $this->em()->clear();
        $reloaded = $this->em()->find(AccountOperation::class, $operation->getId());
        self::assertSame('After', $reloaded?->getDescription());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAmounts(): iterable
    {
        yield 'zero' => ['0'];
        yield 'not a number' => ['abc'];
        yield 'comma as the decimal separator' => ['12,50'];
        yield 'above the upper bound' => ['1000000.00'];
        yield 'far above the upper bound' => ['9999999999'];
    }

    #[DataProvider('invalidAmounts')]
    public function testPostWithAnInvalidAmountIsUnprocessable(string $amount): void
    {
        $account = $this->createAccount();

        $this->client->request(
            'POST',
            '/api/account_operations',
            server: $this->authHeaders() + ['CONTENT_TYPE' => 'application/ld+json'],
            content: json_encode([
                'accountId' => $account->getId(),
                'contractorName' => 'Some Contractor',
                'operationDate' => '2026-01-15',
                'amount' => $amount,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[DataProvider('invalidAmounts')]
    public function testPatchWithAnInvalidAmountIsUnprocessable(string $amount): void
    {
        $operation = $this->createOperation($this->createAccount(), 'Keeps its amount');

        $this->client->request(
            'PATCH',
            '/api/account_operations/'.$operation->getId(),
            server: $this->authHeaders() + ['CONTENT_TYPE' => 'application/merge-patch+json'],
            content: json_encode(['amount' => $amount], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        $this->em()->clear();
        $reloaded = $this->em()->find(AccountOperation::class, $operation->getId());
        self::assertSame(1000, $reloaded?->getAmount());
    }

    /**
     * @param array<string, mixed> $docs
     *
     * @return array<string, mixed>
     */
    private function requestSchemaProperties(array $docs, string $path, string $method): array
    {
        $content = self::objectAt($docs, 'paths', $path, $method, 'requestBody', 'content');
        $media = array_values($content)[0] ?? null;
        self::assertIsArray($media);
        $ref = self::stringAt(self::stringKeyed($media), 'schema', '$ref');
        $schema = self::objectAt($docs, 'components', 'schemas', basename($ref));

        return self::objectAt($schema, 'properties');
    }

    /**
     * @return array<string, mixed>
     */
    private function collection(string $uri): array
    {
        $this->client->request('GET', $uri, server: $this->authHeaders() + ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertResponseIsSuccessful();

        $data = self::decodeObject((string) $this->client->getResponse()->getContent());
        $data['member'] ??= $data['hydra:member'] ?? null;
        $data['view'] ??= $data['hydra:view'] ?? null;

        return $data;
    }

    /**
     * @return list<string>
     */
    private function collectionDescriptions(string $uri): array
    {
        $descriptions = [];
        foreach (self::rowsAt($this->collection($uri), 'member') as $row) {
            if (is_string($row['description'] ?? null)) {
                $descriptions[] = $row['description'];
            }
        }

        return $descriptions;
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken()];
    }

    private function obtainJwtToken(): string
    {
        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'test@example.com', 'password' => 'test1234'], JSON_THROW_ON_ERROR),
        );

        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_string($data['token'] ?? null)) {
            throw new \RuntimeException('Could not obtain a JWT token in the test.');
        }

        return $data['token'];
    }

    private function createAccount(): Account
    {
        $bank = new Bank('Test Bank '.uniqid());
        $this->em()->persist($bank);

        $currency = $this->em()->getRepository(Currency::class)->find('PLN');
        if (null === $currency) {
            $currency = new Currency('PLN');
            $this->em()->persist($currency);
        }

        $accountType = new AccountType('Test Type '.uniqid());
        $this->em()->persist($accountType);

        $household = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        if (null === $household) {
            throw new \RuntimeException('Fixtures must provide exactly one own-funds household.');
        }

        $account = new Account($bank, $currency, $accountType, 'ACC-'.uniqid(), $household);
        $this->em()->persist($account);
        $this->em()->flush();

        return $account;
    }

    private function createOperation(Account $account, string $description): AccountOperation
    {
        $contractor = new Contractor('Test Contractor '.uniqid());
        $this->em()->persist($contractor);

        $operation = new AccountOperation(
            $account,
            $contractor,
            new \DateTimeImmutable(),
            1000,
            $account->getCurrency(),
            description: $description
        );
        $this->em()->persist($operation);
        $this->em()->flush();

        return $operation;
    }
}
