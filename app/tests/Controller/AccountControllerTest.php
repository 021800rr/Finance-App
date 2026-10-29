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
use App\Repository\AccountRepository;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountControllerTest extends WebTestCase
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

    private function createTestAccount(): Account
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

    public function testToggleHiddenRejectsInvalidCsrfToken(): void
    {
        $account = $this->createTestAccount();

        $this->client->request('POST', '/account/' . $account->getId() . '/toggle-hidden', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testToggleHiddenTogglesHiddenFlagWithValidToken(): void
    {
        $account = $this->createTestAccount();
        $accountId = $account->getId();
        self::assertFalse($account->isHidden());

        $crawler = $this->client->request('GET', '/');
        $csrfToken = $crawler
            ->filter('form[action*="' . $accountId . '/toggle-hidden"] input[name="_token"]')
            ->attr('value');

        $this->client->request('POST', '/account/' . $accountId . '/toggle-hidden', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/?show_hidden=0');

        $refreshedAccount = $this->em()->find(Account::class, $accountId);

        self::assertNotNull($refreshedAccount);
        self::assertTrue($refreshedAccount->isHidden());
    }

    public function testToggleHiddenReturnsNotFoundForNonExistentAccount(): void
    {
        $this->createTestAccount();

        $crawler = $this->client->request('GET', '/');
        $csrfToken = $crawler->filter('form[action*="toggle-hidden"] input[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/account/999999999/toggle-hidden', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testToggleHiddenReturnsNotFoundForNonNumericId(): void
    {
        $this->client->request('POST', '/account/alamakota/toggle-hidden');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOperationsRendersSuccessfullyForExistingAccount(): void
    {
        $account = $this->createTestAccount();

        $this->client->request('GET', '/account/' . $account->getId() . '/operations');

        self::assertResponseIsSuccessful();
    }

    public function testOperationsOffersNewOperationOnlyForVisibleAccount(): void
    {
        $account = $this->createTestAccount();
        $link = 'a[href="/operation/new?account='.$account->getId().'"]';

        $this->client->request('GET', '/account/'.$account->getId().'/operations');
        self::assertSelectorExists($link);

        $account->setHidden(true);
        $this->em()->flush();
        $this->client->request('GET', '/account/'.$account->getId().'/operations');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists($link);
    }

    public function testOperationsReturnsNotFoundForNonExistentAccount(): void
    {
        $this->client->request('GET', '/account/999999999/operations');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOperationsReturnsNotFoundForNonNumericId(): void
    {
        $this->client->request('GET', '/account/alamakota/operations');

        self::assertResponseStatusCodeSame(404);
    }

    public function testToggleHiddenPreservesShowHiddenQueryParameter(): void
    {
        $account = $this->createTestAccount();
        $accountId = $account->getId();

        $crawler = $this->client->request('GET', '/?show_hidden=1');
        $csrfToken = $crawler
            ->filter('form[action*="' . $accountId . '/toggle-hidden"] input[name="_token"]')
            ->attr('value');

        $this->client->request('POST', '/account/' . $accountId . '/toggle-hidden?show_hidden=1', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/?show_hidden=1');
    }

    public function testSetMainRejectsInvalidCsrfToken(): void
    {
        $account = $this->createTestAccount();

        $this->client->request('POST', '/account/' . $account->getId() . '/set-main', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testSetMainMarksAccountAsMainAndUnsetsPrevious(): void
    {
        $previousMain = $this->createTestAccount();

        /** @var AccountRepository $accountRepository */
        $accountRepository = self::getContainer()->get(AccountRepository::class);
        $accountRepository->setAsMainAccount($previousMain);

        $newMain = $this->createTestAccount();
        $newMainId = $newMain->getId();

        $crawler = $this->client->request('GET', '/');
        $csrfToken = $crawler
            ->filter('form[action*="' . $newMainId . '/set-main"] input[name="_token"]')
            ->attr('value');

        $this->client->request('POST', '/account/' . $newMainId . '/set-main', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/?show_hidden=0');

        $refreshedPreviousMain = $this->em()->find(Account::class, $previousMain->getId());
        $refreshedNewMain = $this->em()->find(Account::class, $newMainId);

        self::assertNotNull($refreshedPreviousMain);
        self::assertNotNull($refreshedNewMain);
        self::assertFalse($refreshedPreviousMain->isMain());
        self::assertTrue($refreshedNewMain->isMain());
    }

    public function testSetMainReturnsNotFoundForNonNumericId(): void
    {
        $this->client->request('POST', '/account/alamakota/set-main');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOperationsDisplaysSaldoAndOperationInResponse(): void
    {
        $account = $this->createTestAccount();

        $contractor = new Contractor('Test Contractor ' . uniqid());
        $this->em()->persist($contractor);

        $operation = new AccountOperation($account, $contractor, new DateTimeImmutable(), 15000, $account->getCurrency());
        $this->em()->persist($operation);
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/account/' . $account->getId() . '/operations');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('150,00', $crawler->text());
    }
}
