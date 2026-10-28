<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use App\Entity\CurrencyExchangeRate;
use App\Entity\FixedExpense;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Entity\GoldPrice;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DecimalInputValidationApiTest extends WebTestCase
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

    #[DataProvider('invalidValues')]
    public function testPostRejectsInvalidValueAtTheFieldWithoutSaving(
        string $uri,
        string $field,
        string $value,
    ): void {
        $payload = $this->payloadFor($uri);
        $payload[$field] = $value;
        $countsBefore = $this->recordCounts();

        $this->post($uri, $payload);

        self::assertResponseStatusCodeSame(422);
        $error = self::decodeObject((string) $this->client->getResponse()->getContent());
        $violations = self::rowsAt($error, 'violations');
        self::assertCount(1, $violations);
        self::assertSame($field, self::stringAt($violations[0], 'propertyPath'));
        self::assertSame($countsBefore, $this->recordCounts());
    }

    #[DataProvider('validValues')]
    public function testPostAcceptsValuesAtTheLimits(string $uri, string $field, string $value): void
    {
        $payload = $this->payloadFor($uri);
        $payload[$field] = $value;

        $this->post($uri, $payload);

        self::assertResponseStatusCodeSame(201);
        $data = self::decodeObject((string) $this->client->getResponse()->getContent());
        $responseField = match ($field) {
            'amountFrom' => 'outAmount',
            'amountTo' => 'inAmount',
            default => $field,
        };
        self::assertSame('amountFrom' === $field ? '-'.$value : $value, self::stringAt($data, $responseField));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidValues(): iterable
    {
        foreach (self::fields() as [$uri, $field, , , $below, $above]) {
            foreach ([
                'below minimum' => $below,
                'above maximum' => $above,
                '17 digits' => str_repeat('9', 17),
                '33 digits' => str_repeat('9', 33),
                'negative 33 digits' => '-'.str_repeat('9', 33),
                'not numeric' => 'abc',
            ] as $case => $value) {
                yield $uri.' '.$field.' '.$case => [$uri, $field, $value];
            }
        }
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function validValues(): iterable
    {
        foreach (self::fields() as [$uri, $field, $min, $max]) {
            yield $uri.' '.$field.' minimum' => [$uri, $field, $min];
            yield $uri.' '.$field.' maximum' => [$uri, $field, $max];
        }
    }

    /**
     * @return list<array{string, string, string, string, string, string}>
     */
    private static function fields(): array
    {
        return [
            ['/api/account_operations', 'amount', '-999999.99', '999999.99', '-1000000.00', '1000000.00'],
            ['/api/fixed_expenses', 'amount', '-999999.99', '-0.01', '-1000000.00', '0.00'],
            ['/api/gold_prices', 'pricePln', '0.01', '999999.99', '0.00', '1000000.00'],
            ['/api/currency_exchange_rates', 'rateToPln', '0.0001', '99.9999', '0.0000', '100.0000'],
            ['/api/gold_assets', 'ounces', '0.0001', '999.9999', '0.0000', '1000.0000'],
            ['/api/transfers', 'amountFrom', '0.01', '999999.99', '0.00', '1000000.00'],
            ['/api/transfers', 'amountTo', '0.01', '999999.99', '0.00', '1000000.00'],
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function payloadFor(string $uri): array
    {
        $account = $this->account('Bank1-ROR-PLN');
        if ('/api/gold_assets' === $uri || '/api/gold_prices' === $uri) {
            $form = $this->em()->getRepository(GoldForm::class)->findOneBy([]);
            self::assertNotNull($form);
            if ('/api/gold_assets' === $uri) {
                return ['formId' => $form->getId(), 'description' => 'Decimal validation', 'ounces' => '1.0000'];
            }

            $asset = new GoldAsset($form, 10000, 'Decimal validation');
            $this->em()->persist($asset);
            $this->em()->flush();

            return ['goldAssetId' => $asset->getId(), 'priceDate' => '2026-01-15', 'pricePln' => '100.00'];
        }

        return match ($uri) {
            '/api/account_operations' => [
                'accountId' => $account->getId(),
                'contractorName' => 'Decimal validation '.uniqid(),
                'operationDate' => '2026-01-15',
                'amount' => '10.00',
            ],
            '/api/fixed_expenses' => [
                'contractorName' => 'Decimal validation '.uniqid(),
                'day' => 10,
                'amount' => '-10.00',
            ],
            '/api/currency_exchange_rates' => [
                'bankId' => $account->getBank()->getId(),
                'currencyCode' => 'CHF',
                'rateDate' => '2026-01-15',
                'rateToPln' => '4.0000',
            ],
            '/api/transfers' => [
                'fromAccountId' => $account->getId(),
                'toAccountId' => $this->account('Bank1-WAK-CHF')->getId(),
                'operationDate' => '2026-01-15',
                'amountFrom' => '10.00',
                'amountTo' => '2.00',
            ],
            default => throw new \InvalidArgumentException('Unknown API URI: '.$uri),
        };
    }

    private function account(string $number): Account
    {
        $account = $this->em()->getRepository(Account::class)->findOneBy(['accountNumber' => $number]);
        self::assertNotNull($account);

        return $account;
    }

    /**
     * @return array<class-string, int>
     */
    private function recordCounts(): array
    {
        $counts = [];
        foreach ([
            AccountOperation::class,
            Contractor::class,
            CurrencyExchangeRate::class,
            FixedExpense::class,
            GoldAsset::class,
            GoldPrice::class,
        ] as $entityClass) {
            $counts[$entityClass] = $this->em()->getRepository($entityClass)->count([]);
        }

        return $counts;
    }

    /**
     * @param array<string, int|string> $payload
     */
    private function post(string $uri, array $payload): void
    {
        $this->client->jsonRequest('POST', '/api/login_check', [
            'email' => 'test@example.com',
            'password' => 'test1234',
        ]);
        self::assertResponseIsSuccessful();
        $token = self::stringAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'token');

        $this->client->request('POST', $uri, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], content: json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
