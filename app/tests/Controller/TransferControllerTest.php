<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\Household;
use App\Tests\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TransferControllerTest extends WebTestCase
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

    public function testNewFormRendersSuccessfully(): void
    {
        $this->client->request('GET', '/transfer/new');

        self::assertResponseIsSuccessful();
    }

    public function testNewSubmittingSameCurrencyTransferCreatesOperationsAndRedirects(): void
    {
        $fromAccount = $this->createAccount('PLN');
        $toAccount = $this->createAccount('PLN');

        $crawler = $this->client->request('GET', '/transfer/new');
        $form = $crawler->filter('#transfer_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'transfer[fromAccount]' => (string) $fromAccount->getId(),
            'transfer[toAccount]' => (string) $toAccount->getId(),
            'transfer[amountFrom]' => '100',
        ]);

        self::assertResponseRedirects();
        self::assertStringStartsWith(
            '/account/' . $fromAccount->getId() . '/operations',
            (string) $this->client->getResponse()->headers->get('Location')
        );
    }

    public function testNewSubmittingDifferentCurrencyWithoutAmountToShowsError(): void
    {
        $fromAccount = $this->createAccount('PLN');
        $toAccount = $this->createAccount('CHF');

        $crawler = $this->client->request('GET', '/transfer/new');
        $form = $crawler->filter('#transfer_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'transfer[fromAccount]' => (string) $fromAccount->getId(),
            'transfer[toAccount]' => (string) $toAccount->getId(),
            'transfer[amountFrom]' => '100',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#transfer_amountTo_error1');
    }

    private function createAccount(string $currencyCode): Account
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);

        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->find($currencyCode);
        if ($currency === null) {
            $currency = new Currency($currencyCode);
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
}
