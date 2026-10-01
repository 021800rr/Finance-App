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

final class HomeControllerTest extends WebTestCase
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

    public function testIndexRendersSuccessfully(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    public function testIndexHidesHiddenAccountsByDefault(): void
    {
        $account = $this->createAccount(hidden: true);

        $this->client->request('GET', '/');

        self::assertSelectorNotExists('form[action*="' . $account->getId() . '/toggle-hidden"]');
    }

    public function testIndexShowsHiddenAccountsWhenRequested(): void
    {
        $account = $this->createAccount(hidden: true);

        $this->client->request('GET', '/?show_hidden=1');

        self::assertSelectorExists('form[action*="' . $account->getId() . '/toggle-hidden"]');
    }

    public function testIndexDisplaysAccountBalance(): void
    {
        $account = $this->createAccount();
        $this->createOperationForAccount($account, 15000);

        $crawler = $this->client->request('GET', '/');

        self::assertStringContainsString('150,00', $crawler->filter('table')->first()->text());
    }

    private function createAccount(bool $hidden = false): Account
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
        $account->setHidden($hidden);
        $this->em()->persist($account);
        $this->em()->flush();

        return $account;
    }

    private function createOperationForAccount(Account $account, int $amountMinorUnits): void
    {
        $contractor = new Contractor('Test Contractor ' . uniqid());
        $this->em()->persist($contractor);

        $operation = new AccountOperation($account, $contractor, new DateTimeImmutable(), $amountMinorUnits, $account->getCurrency());
        $this->em()->persist($operation);
        $this->em()->flush();
    }
}
