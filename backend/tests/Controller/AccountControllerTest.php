<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Account;
use App\Tests\DatabaseTestTrait;
use App\Tests\LosesMainAccountRaceTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountControllerTest extends WebTestCase
{
    use DatabaseTestTrait;
    use LosesMainAccountRaceTrait;
    use LogsInTrait;
    use TestDataTrait;

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

    public function testToggleHiddenRejectsInvalidCsrfToken(): void
    {
        $account = $this->createAccount();

        $this->client->request('POST', '/account/'.$account->getId().'/toggle-hidden', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testToggleHiddenTogglesHiddenFlagWithValidToken(): void
    {
        $account = $this->createAccount();
        $accountId = $account->getId();
        self::assertFalse($account->isHidden());

        $crawler = $this->client->request('GET', '/');
        $csrfToken = $crawler
            ->filter('form[action*="'.$accountId.'/toggle-hidden"] input[name="_token"]')
            ->attr('value');

        $this->client->request('POST', '/account/'.$accountId.'/toggle-hidden', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/?show_hidden=0');

        $refreshedAccount = $this->em()->find(Account::class, $accountId);

        self::assertNotNull($refreshedAccount);
        self::assertTrue($refreshedAccount->isHidden());
    }

    public function testToggleHiddenReturnsNotFoundForNonExistentAccount(): void
    {
        $this->createAccount();

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
        $account = $this->createAccount();

        $this->client->request('GET', '/account/'.$account->getId().'/operations');

        self::assertResponseIsSuccessful();
    }

    public function testOperationsOffersNewOperationOnlyForVisibleAccount(): void
    {
        $account = $this->createAccount();
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
        $account = $this->createAccount();
        $accountId = $account->getId();

        $crawler = $this->client->request('GET', '/?show_hidden=1');
        $csrfToken = $crawler
            ->filter('form[action*="'.$accountId.'/toggle-hidden"] input[name="_token"]')
            ->attr('value');

        $this->client->request('POST', '/account/'.$accountId.'/toggle-hidden?show_hidden=1', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/?show_hidden=1');
    }

    public function testSetMainRejectsInvalidCsrfToken(): void
    {
        $account = $this->createAccount();

        $this->client->request('POST', '/account/'.$account->getId().'/set-main', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testSetMainMarksAccountAsMainAndUnsetsPrevious(): void
    {
        $previousMain = $this->createAccount(main: true);

        $newMain = $this->createAccount();
        $newMainId = $newMain->getId();

        $crawler = $this->client->request('GET', '/');
        $csrfToken = $crawler
            ->filter('form[action*="'.$newMainId.'/set-main"] input[name="_token"]')
            ->attr('value');

        $this->client->request('POST', '/account/'.$newMainId.'/set-main', [
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

    public function testSetMainLosingARaceShowsAMessageAndKeepsOneMainAccount(): void
    {
        $account = $this->createAccount();
        $accountId = $account->getId();
        $rivalId = $this->createAccount()->getId();

        $crawler = $this->client->request('GET', '/');
        $csrfToken = $crawler
            ->filter('form[action*="'.$accountId.'/set-main"] input[name="_token"]')
            ->attr('value');
        $this->loseTheNextMainAccountRace($this->client, $rivalId);

        $this->client->request('POST', '/account/'.$accountId.'/set-main', ['_token' => $csrfToken]);

        self::assertResponseRedirects();
        self::assertSame(
            1,
            $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM account WHERE is_main = true'),
        );

        // The failed flush closed the entity manager; in production the redirect is a new request anyway.
        $this->client->enableReboot();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Another change of the main account happened at the same time.');
    }

    public function testSetMainReturnsNotFoundForNonNumericId(): void
    {
        $this->client->request('POST', '/account/alamakota/set-main');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOperationsDisplaysSaldoAndOperationInResponse(): void
    {
        $account = $this->createAccount();

        $this->createOperation($account, 15000);

        $crawler = $this->client->request('GET', '/account/'.$account->getId().'/operations');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('150,00', $crawler->text());
    }
}
