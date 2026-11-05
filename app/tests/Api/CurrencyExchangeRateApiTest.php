<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Bank;
use App\Entity\CurrencyExchangeRate;
use App\Tests\DatabaseTestTrait;
use App\Tests\JwtTrait;
use App\Tests\TestDataTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CurrencyExchangeRateApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use ReadsJsonTrait;
    use TestDataTrait;
    use JwtTrait;

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

    public function testBankWithVisibleForeignCurrencyAccountIsAccepted(): void
    {
        $countBefore = $this->em()->getRepository(CurrencyExchangeRate::class)->count([]);

        $this->postRate($this->createBankWithAccounts(['CHF']), 'CHF');

        self::assertResponseStatusCodeSame(201);
        self::assertSame($countBefore + 1, $this->em()->getRepository(CurrencyExchangeRate::class)->count([]));
    }

    /**
     * @param list<string> $accountCurrencies
     */
    #[DataProvider('banksWithoutVisibleForeignCurrencyAccount')]
    public function testBankWithoutVisibleForeignCurrencyAccountIsRejectedWithoutSaving(
        array $accountCurrencies,
        bool $hidden,
    ): void {
        $countBefore = $this->em()->getRepository(CurrencyExchangeRate::class)->count([]);

        $this->postRate($this->createBankWithAccounts($accountCurrencies, $hidden), 'CHF');

        self::assertSame(
            ['bankId' => 'This bank has no visible foreign-currency account. Please select another bank.'],
            $this->violationMessages(),
        );
        self::assertSame($countBefore, $this->em()->getRepository(CurrencyExchangeRate::class)->count([]));
    }

    /**
     * @return iterable<string, array{list<string>, bool}>
     */
    public static function banksWithoutVisibleForeignCurrencyAccount(): iterable
    {
        yield 'no accounts' => [[], false];
        yield 'only a PLN account' => [['PLN'], false];
        yield 'only a hidden CHF account' => [['CHF'], true];
    }

    #[DataProvider('plnCodes')]
    public function testPlnCurrencyIsRejectedWithoutSaving(string $currencyCode): void
    {
        $countBefore = $this->em()->getRepository(CurrencyExchangeRate::class)->count([]);

        $this->postRate($this->createBankWithAccounts(['CHF']), $currencyCode);

        self::assertSame(
            ['currencyCode' => 'A rate cannot be added for PLN. Please select another currency.'],
            $this->violationMessages(),
        );
        self::assertSame($countBefore, $this->em()->getRepository(CurrencyExchangeRate::class)->count([]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function plnCodes(): iterable
    {
        yield 'PLN' => ['PLN'];
        yield 'PLN with spaces' => [' PLN '];
        yield 'PLN with a NUL byte, which trim() also strips' => ["PLN\0"];
    }

    public function testOpenApiDocumentsWhichBanksAndCurrenciesAreAccepted(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        self::assertResponseIsSuccessful();

        $docs = self::decodeObject((string) $this->client->getResponse()->getContent());
        $create = self::objectAt($docs, 'paths', '/api/currency_exchange_rates', 'post');

        self::assertIsString($create['description'] ?? null);
        self::assertStringContainsString('the bank must have at least one visible', $create['description']);
        self::assertStringContainsString('the currency must not be PLN', $create['description']);
        self::assertSame(
            'Validation failed, e.g. the bank has no visible foreign-currency account or currencyCode is PLN.',
            self::objectAt($create, 'responses', '422')['description'] ?? null,
        );
    }

    /**
     * @return array<mixed>
     */
    private function violationMessages(): array
    {
        self::assertResponseStatusCodeSame(422);
        $violations = self::rowsAt(
            self::decodeObject((string) $this->client->getResponse()->getContent()),
            'violations',
        );

        return array_column($violations, 'message', 'propertyPath');
    }

    private function postRate(Bank $bank, string $currencyCode): void
    {
        $this->client->request('POST', '/api/currency_exchange_rates', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(),
            'CONTENT_TYPE' => 'application/ld+json',
        ], content: json_encode([
            'bankId' => $bank->getId(),
            'currencyCode' => $currencyCode,
            'rateDate' => '2026-01-15',
            'rateToPln' => '4.0000',
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<string> $accountCurrencies
     */
    private function createBankWithAccounts(array $accountCurrencies, bool $hidden = false): Bank
    {
        $bank = $this->createBank();
        foreach ($accountCurrencies as $code) {
            $this->createAccount($code, $bank, hidden: $hidden);
        }

        return $bank;
    }
}
