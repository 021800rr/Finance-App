<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\DatabaseTestTrait;
use App\Tests\JwtTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * GET /api/summary as clients read it: an account row names its fields as GET /api/accounts does, and a field with no
 * value is left out, never sent as null.
 */
final class SummaryApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use JwtTrait;
    use ReadsJsonTrait;
    use TestDataTrait;

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

    public function testAnswersEveryPartOfTheSummary(): void
    {
        self::assertSame(
            [
                'accounts', 'byType', 'byHousehold', 'ownFundsHouseholdName', 'gold', 'retirementBalance',
                'retirementBalanceWithGold', 'accountsMissingRate',
            ],
            array_keys($this->getSummary()),
        );
    }

    public function testAnAccountRowNamesItsFieldsAsTheAccountsDoAndAPlnAccountHasNoRate(): void
    {
        $account = $this->createAccount(main: true);
        $this->createOperation($account, -5050, new \DateTimeImmutable('-1 day'));

        self::assertSame(
            [
                'id' => $account->getId(),
                'bankName' => $account->getBankName(),
                'typeName' => $account->getTypeName(),
                'currencyCode' => 'PLN',
                'accountNumber' => $account->getAccountNumber(),
                'hidden' => false,
                'householdName' => $account->getHouseholdName(),
                'isOwnFunds' => true,
                'isRetirement' => false,
                'saldo' => ['amount' => '-50.50', 'currency' => 'PLN'],
                'missingRate' => false,
                'isMain' => true,
            ],
            $this->accountRow($this->getSummary(), $account->getAccountNumber()),
        );
    }

    public function testAnAccountRowInAnotherCurrencyCarriesTheLatestRateOfItsBank(): void
    {
        $bank = $this->createBank();
        $account = $this->createAccount('CHF', bank: $bank);
        $this->createRate($bank, 'CHF', new \DateTimeImmutable('-10 days'), 40000);
        $this->createRate($bank, 'CHF', new \DateTimeImmutable('-1 day'), 41234);

        $row = $this->accountRow($this->getSummary(), $account->getAccountNumber());

        self::assertSame(['amount' => '4.1234', 'currency' => 'PLN'], $row['rate'] ?? null);
        self::assertFalse($row['missingRate'] ?? null);
    }

    public function testAnAccountRowWhoseBankHasNoRateHasNoneAndSaysItIsMissing(): void
    {
        $account = $this->createAccount('CHF');

        $summary = $this->getSummary();
        $row = $this->accountRow($summary, $account->getAccountNumber());

        self::assertArrayNotHasKey('rate', $row);
        self::assertTrue($row['missingRate'] ?? null);
        $missingRate = $summary['accountsMissingRate'] ?? null;
        self::assertIsArray($missingRate);
        self::assertContains($account->getBankName().' '.$account->getAccountNumber(), $missingRate);
    }

    public function testInJsonLdTheRowsAndTotalsAreTypedButGetNoGeneratedId(): void
    {
        $account = $this->createAccount();

        $summary = $this->getSummary('application/ld+json');

        // A generated @id (/.well-known/genid/...) would differ in every answer while naming nothing.
        $row = $this->accountRow($summary, $account->getAccountNumber());
        self::assertSame('SummaryAccount', $row['@type'] ?? null);
        self::assertArrayNotHasKey('@id', $row);
        $total = self::objectAt($summary, 'byType', $account->getTypeName());
        self::assertSame('SummaryTypeTotal', $total['@type'] ?? null);
        self::assertArrayNotHasKey('@id', $total);
    }

    public function testOpenApiDescribesTheRowsTheTotalsAndTheAccountsMissingARate(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        self::assertResponseIsSuccessful();
        $docs = self::decodeObject((string) $this->client->getResponse()->getContent());

        $summary = self::objectAt($docs, 'components', 'schemas', 'Summary', 'properties');
        self::assertSame(
            '#/components/schemas/SummaryAccount_noid',
            self::stringAt($summary, 'accounts', 'items', '$ref'),
        );
        self::assertSame(
            '#/components/schemas/SummaryTypeTotal_noid',
            self::stringAt($summary, 'byType', 'additionalProperties', '$ref'),
        );
        self::assertSame('string', self::stringAt($summary, 'accountsMissingRate', 'items', 'type'));
    }

    public function testWithoutATokenItIsUnauthorized(): void
    {
        $this->client->request('GET', '/api/summary');

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return array<string, mixed>
     */
    private function getSummary(string $accept = 'application/json'): array
    {
        $this->client->request('GET', '/api/summary', server: $this->authHeaders() + ['HTTP_ACCEPT' => $accept]);
        self::assertResponseIsSuccessful();

        return self::decodeObject((string) $this->client->getResponse()->getContent());
    }

    /**
     * @param array<string, mixed> $summary
     *
     * @return array<string, mixed>
     */
    private function accountRow(array $summary, string $accountNumber): array
    {
        foreach (self::rowsAt($summary, 'accounts') as $row) {
            if (($row['accountNumber'] ?? null) === $accountNumber) {
                return $row;
            }
        }

        self::fail('The summary has no row of account '.$accountNumber.'.');
    }
}
