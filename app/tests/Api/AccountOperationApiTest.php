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
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountOperationApiTest extends WebTestCase
{
    use DatabaseTestTrait;

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

    public function testQueryParameterCombinesWithAccountId(): void
    {
        $marker = uniqid();
        $accountA = $this->createAccount();
        $accountB = $this->createAccount();
        $this->createOperation($accountA, "Shared {$marker} A");
        $this->createOperation($accountB, "Shared {$marker} B");

        $descriptions = $this->collectionDescriptions(
            '/api/account_operations?accountId='.$accountA->getId().'&q='.rawurlencode("Shared {$marker}"),
        );

        self::assertSame(["Shared {$marker} A"], $descriptions);
    }

    public function testAccountIdParameterRestrictsTheCollectionToThatAccount(): void
    {
        $marker = uniqid();
        $accountA = $this->createAccount();
        $accountB = $this->createAccount();
        $this->createOperation($accountA, "In A {$marker}");
        $this->createOperation($accountB, "In B {$marker}");

        $descriptions = $this->collectionDescriptions('/api/account_operations?accountId='.$accountA->getId());

        self::assertSame(["In A {$marker}"], $descriptions);
    }

    public function testQueryParameterShorterThanTwoCharactersIsRejected(): void
    {
        $this->client->request('GET', '/api/account_operations?q=a', server: $this->authHeaders());

        self::assertResponseStatusCodeSame(422);
    }

    public function testNonNumericAccountIdIsRejected(): void
    {
        $this->client->request('GET', '/api/account_operations?accountId=abc', server: $this->authHeaders());

        self::assertResponseStatusCodeSame(422);
    }

    public function testAccountIdAboveInt4MaxIsRejected(): void
    {
        $this->client->request('GET', '/api/account_operations?accountId='.PHP_INT_MAX, server: $this->authHeaders());

        self::assertResponseStatusCodeSame(422);
    }

    public function testAbsurdPageNumberReturnsAnEmptyPageInsteadOfFailing(): void
    {
        $descriptions = $this->collectionDescriptions('/api/account_operations?page='.PHP_INT_MAX);

        self::assertSame([], $descriptions);
    }

    /**
     * @return list<string>
     */
    private function collectionDescriptions(string $uri): array
    {
        $this->client->request('GET', $uri, server: $this->authHeaders() + ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertResponseIsSuccessful();

        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $members = is_array($data) ? ($data['hydra:member'] ?? $data['member'] ?? null) : null;
        if (!is_array($members)) {
            throw new \RuntimeException('Expected a JSON-LD collection with a member list.');
        }

        $descriptions = [];
        foreach ($members as $row) {
            if (is_array($row) && is_string($row['description'] ?? null)) {
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

        $operation = new AccountOperation($account, $contractor, new \DateTimeImmutable(), 1000, $account->getCurrency());
        $operation->setDescription($description);
        $this->em()->persist($operation);
        $this->em()->flush();

        return $operation;
    }
}
