<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\Household;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SearchControllerTest extends WebTestCase
{
    use DatabaseTestTrait;
    use LogsInTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->initEntityManager($this->client->getKernel());
        $this->logIn();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testIndexRendersSuccessfullyWithoutQuery(): void
    {
        $this->client->request('GET', '/search');

        self::assertResponseIsSuccessful();
    }

    public function testIndexReturnsEmptyResultsForBlankQuery(): void
    {
        $this->client->request('GET', '/search?q=');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('table tbody tr[data-operation-id]');
    }

    public function testIndexFindsMatchingOperationByDescription(): void
    {
        $account = $this->createAccount();
        $this->createOperation($account, 15000, 'Unique test description ' . uniqid());

        $crawler = $this->client->request('GET', '/search?q=Unique+test+description');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('table tbody tr')->count());
    }

    public function testIndexReturnsNoResultsForNonMatchingQuery(): void
    {
        $this->createAccount();

        $this->client->request('GET', '/search?q=' . uniqid());

        self::assertResponseIsSuccessful();
    }

    public function testIndexRespectsPageParameter(): void
    {
        $this->client->request('GET', '/search?q=test&page=2');

        self::assertResponseIsSuccessful();
    }

    public function testLongNumericQueryFindsMatchingDescription(): void
    {
        $query = str_repeat('9', 33);
        $description = 'Reference '.$query;
        $this->createOperation($this->createAccount(), 15000, $description);

        $this->client->request('GET', '/search', ['q' => $query]);

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('table tbody', $description);
    }

    private function createAccount(): Account
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);

        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->findOneBy(['code' => 'PLN']);
        if ($currency === null) {
            $currency = new Currency('PLN');
            $this->em()->persist($currency);
        }

        $accountType = new AccountType('Test Type ' . uniqid());
        $this->em()->persist($accountType);

        $household = $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => true]);
        if (null === $household) {
            throw new \RuntimeException('Fixtures must provide exactly one own-funds household.');
        }

        $account = new Account($bank, $currency, $accountType, 'ACC-' . uniqid(), $household);
        $this->em()->persist($account);
        $this->em()->flush();

        return $account;
    }

    private function createOperation(Account $account, int $amountMinorUnits, string $description): void
    {
        $contractor = new Contractor('Test Contractor ' . uniqid());
        $this->em()->persist($contractor);

        $operation = new AccountOperation(
            $account,
            $contractor,
            new DateTimeImmutable(),
            $amountMinorUnits,
            $account->getCurrency(),
            description: $description
        );
        $this->em()->persist($operation);
        $this->em()->flush();
    }
}
