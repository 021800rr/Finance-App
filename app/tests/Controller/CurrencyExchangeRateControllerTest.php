<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Account;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\Entity\Household;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CurrencyExchangeRateControllerTest extends WebTestCase
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
        $this->client->request('GET', '/rate');

        self::assertResponseIsSuccessful();
    }

    public function testChartGroupsDailyRatesByBankAndDateAndConvertsMinorUnits(): void
    {
        $bankA = $this->createBank();
        $bankB = $this->createBank();
        $chf = $this->getOrCreateCurrency('CHF');
        $eur = $this->getOrCreateCurrency('EUR');

        foreach ([
            [$bankA, $chf, '2026-10-26', 45000],
            [$bankA, $chf, '2026-10-26', 43210],
            [$bankB, $chf, '2026-10-26', 44000],
            [$bankA, $eur, '2026-10-26', 48000],
            [$bankA, $chf, '2026-10-25', 91000],
        ] as [$bank, $currency, $date, $minorUnits]) {
            $rate = new CurrencyExchangeRate($bank, $currency, new DateTimeImmutable($date), $minorUnits);
            $this->em()->persist($rate);
        }
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/rate');

        self::assertResponseIsSuccessful();
        $chartJson = $crawler->filter('#ratesChart')->attr('data-chart');
        self::assertIsString($chartJson);
        $chart = json_decode($chartJson, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($chart);
        self::assertSame(['2026-10-25' => 9.1, '2026-10-26' => 4.321], $chart[$bankA->getName()]);
        self::assertSame(['2026-10-26' => 4.4], $chart[$bankB->getName()]);
    }

    public function testIndexSubmittingWithValidDataCreatesRateAndRedirects(): void
    {
        $bank = $this->createBankWithForeignCurrencyAccount();
        $chf = $this->getOrCreateCurrency('CHF');

        $crawler = $this->client->request('GET', '/rate');
        $form = $crawler->filter('#rate_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'currency_exchange_rate[bank]' => (string) $bank->getId(),
            'currency_exchange_rate[currency]' => $chf->getCode(),
            'currency_exchange_rate[rateDate]' => new DateTimeImmutable()->format('Y-m-d'),
            'currency_exchange_rate[rateToPln]' => '4.5',
        ]);

        self::assertResponseRedirects('/rate');

        $rates = $this->em()->getRepository(CurrencyExchangeRate::class)->findBy(['bank' => $bank]);

        self::assertCount(1, $rates);
        self::assertSame(45000, $rates[0]->getRateToPln());
    }

    public function testIndexSubmittingWithNonPositiveRateShowsValidationError(): void
    {
        $bank = $this->createBankWithForeignCurrencyAccount();
        $chf = $this->getOrCreateCurrency('CHF');

        $crawler = $this->client->request('GET', '/rate');
        $form = $crawler->selectButton('Add')->form();

        $this->client->submit($form, [
            'currency_exchange_rate[bank]' => (string) $bank->getId(),
            'currency_exchange_rate[currency]' => $chf->getCode(),
            'currency_exchange_rate[rateDate]' => (new DateTimeImmutable())->format('Y-m-d'),
            'currency_exchange_rate[rateToPln]' => '0',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#rate_form .invalid-feedback.d-block');
    }

    #[DataProvider('blankChoices')]
    public function testIndexWithBlankChoiceShowsFieldErrorWithoutSaving(
        string $field,
        ?string $value,
        string $message,
    ): void {
        $bank = $this->createBankWithForeignCurrencyAccount();
        $countBefore = $this->em()->getRepository(CurrencyExchangeRate::class)->count([]);
        $crawler = $this->client->request('GET', '/rate');
        $token = $crawler->filter('#rate_form input[name="currency_exchange_rate[_token]"]')->attr('value');
        self::assertIsString($token);
        $data = [
            '_token' => $token,
            'bank' => (string) $bank->getId(),
            'currency' => 'CHF',
            'rateDate' => new DateTimeImmutable()->format('Y-m-d'),
            'rateToPln' => '4.5',
        ];
        if (null === $value) {
            unset($data[$field]);
        } else {
            $data[$field] = $value;
        }

        $this->client->request('POST', '/rate', ['currency_exchange_rate' => $data]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#currency_exchange_rate_'.$field.'.is-invalid');
        self::assertSelectorTextContains('#currency_exchange_rate_'.$field.' + .invalid-feedback.d-block', $message);
        self::assertSame($countBefore, $this->em()->getRepository(CurrencyExchangeRate::class)->count([]));
    }

    /**
     * @return array<string, array{string, ?string, string}>
     */
    public static function blankChoices(): array
    {
        return [
            'bank empty' => ['bank', '', 'Please select a bank.'],
            'bank missing' => ['bank', null, 'Please select a bank.'],
            'currency empty' => ['currency', '', 'Please select a currency.'],
            'currency missing' => ['currency', null, 'Please select a currency.'],
        ];
    }

    public function testIndexOffersOnlyBanksWithVisibleForeignCurrencyAccountAndNoPln(): void
    {
        $offered = $this->createBankWithAccount('CHF');
        $notOffered = [
            $this->createBankWithAccount('CHF', hidden: true),
            $this->createBankWithAccount('PLN'),
            $this->createBank(),
        ];

        $this->client->request('GET', '/rate');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#currency_exchange_rate_bank option[value="'.$offered->getId().'"]');
        foreach ($notOffered as $bank) {
            self::assertSelectorNotExists('#currency_exchange_rate_bank option[value="'.$bank->getId().'"]');
        }
        self::assertSelectorExists('#currency_exchange_rate_currency option[value="CHF"]');
        self::assertSelectorNotExists('#currency_exchange_rate_currency option[value="PLN"]');
    }

    #[DataProvider('unavailableChoices')]
    public function testIndexWithUnavailableChoiceShowsFieldErrorWithoutSaving(string $field, ?string $value): void
    {
        $data = [
            'bank' => (string) $this->createBankWithForeignCurrencyAccount()->getId(),
            'currency' => 'CHF',
            'rateDate' => new DateTimeImmutable()->format('Y-m-d'),
            'rateToPln' => '4.5',
        ];
        $data[$field] = $value ?? (string) $this->createBankWithAccount('CHF', hidden: true)->getId();
        $countBefore = $this->em()->getRepository(CurrencyExchangeRate::class)->count([]);
        $crawler = $this->client->request('GET', '/rate');
        $token = $crawler->filter('#rate_form input[name="currency_exchange_rate[_token]"]')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/rate', ['currency_exchange_rate' => $data + ['_token' => $token]]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#currency_exchange_rate_'.$field.'.is-invalid');
        self::assertSelectorExists('#currency_exchange_rate_'.$field.' + .invalid-feedback.d-block');
        self::assertSame($countBefore, $this->em()->getRepository(CurrencyExchangeRate::class)->count([]));
    }

    /**
     * Null stands for a bank whose only foreign-currency account is hidden, created inside the test.
     *
     * @return array<string, array{string, ?string}>
     */
    public static function unavailableChoices(): array
    {
        return [
            'bank with hidden account only' => ['bank', null],
            'bank above SQL integer' => ['bank', '2147483648'],
            'bank huge' => ['bank', str_repeat('9', 33)],
            'currency PLN' => ['currency', 'PLN'],
        ];
    }

    public function testDeleteReturnsNotFoundForNonNumericId(): void
    {
        $this->client->request('POST', '/rate/alamakota/delete');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteRejectsInvalidCsrfToken(): void
    {
        $rate = $this->createRateInDatabase();

        $this->client->request('POST', '/rate/' . $rate->getId() . '/delete', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteRemovesRateWithValidCsrfToken(): void
    {
        $rate = $this->createRateInDatabase();
        $rateId = $rate->getId();

        $crawler = $this->client->request('GET', '/rate');
        $csrfToken = $crawler->filter('form[action*="' . $rateId . '/delete"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/rate/' . $rateId . '/delete', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/rate');

        $deleted = $this->em()->find(CurrencyExchangeRate::class, $rateId);

        self::assertNull($deleted);
    }

    private function createBank(): Bank
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);
        $this->em()->flush();

        return $bank;
    }

    private function getOrCreateCurrency(string $code): Currency
    {
        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->find($code);
        if (null === $currency) {
            $currency = new Currency($code);
            $this->em()->persist($currency);
            $this->em()->flush();
        }

        return $currency;
    }

    private function createRateInDatabase(): CurrencyExchangeRate
    {
        $bank = $this->createBank();
        $chf = $this->getOrCreateCurrency('CHF');

        $rate = new CurrencyExchangeRate($bank, $chf, new DateTimeImmutable(), 45000);
        $this->em()->persist($rate);
        $this->em()->flush();

        return $rate;
    }

    private function createBankWithForeignCurrencyAccount(): Bank
    {
        return $this->createBankWithAccount('CHF');
    }

    private function createBankWithAccount(string $currencyCode, bool $hidden = false): Bank
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);

        $currency = $this->getOrCreateCurrency($currencyCode);

        $accountType = new AccountType('Test Type ' . uniqid());
        $this->em()->persist($accountType);

        $household = new Household('Test Household ' . uniqid());
        $this->em()->persist($household);

        $account = new Account($bank, $currency, $accountType, 'ACC-' . uniqid(), $household);
        $account->setHidden($hidden);
        $this->em()->persist($account);

        $this->em()->flush();

        return $bank;
    }
}
