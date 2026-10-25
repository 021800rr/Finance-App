<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use App\Entity\CurrencyExchangeRate;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Entity\GoldPrice;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DateValidationApiTest extends WebTestCase
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

    /**
     * @param class-string<AccountOperation|GoldPrice|CurrencyExchangeRate> $entityClass
     */
    #[DataProvider('invalidPostDates')]
    public function testPostRejectsInvalidDatesWithoutWritingRecords(
        string $uri,
        string $field,
        string $entityClass,
        string $date
    ): void
    {
        $payload = $this->payloadFor($uri) + [$field => $date];
        $countBefore = $this->em()->getRepository($entityClass)->count([]);
        $contractorsBefore = $this->em()->getRepository(Contractor::class)->count([]);

        $this->requestJson('POST', $uri, $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($countBefore, $this->em()->getRepository($entityClass)->count([]));
        self::assertSame($contractorsBefore, $this->em()->getRepository(Contractor::class)->count([]));
    }

    #[DataProvider('invalidDates')]
    public function testPatchRejectsInvalidDatesWithoutChangingTheOperation(string $date): void
    {
        $operation = $this->createOperation();
        $id = $operation->getId();
        $contractorId = $operation->getContractor()->getId();
        $contractorsBefore = $this->em()->getRepository(Contractor::class)->count([]);

        $this->requestJson('PATCH', '/api/account_operations/'.$id, [
            'operationDate' => $date,
            'amount' => '20.00',
            'description' => 'Changed',
            'confirmed' => true,
            'contractorName' => 'New contractor '.uniqid(),
        ]);

        self::assertResponseStatusCodeSame(422);
        $this->em()->clear();
        $unchanged = $this->em()->find(AccountOperation::class, $id);
        self::assertNotNull($unchanged);
        self::assertSame('2026-01-15 00:00:00', $unchanged->getOperationDate()->format('Y-m-d H:i:s'));
        self::assertSame(1000, $unchanged->getAmount());
        self::assertSame('Original', $unchanged->getDescription());
        self::assertFalse($unchanged->isConfirmed());
        self::assertSame($contractorId, $unchanged->getContractor()->getId());
        self::assertSame($contractorsBefore, $this->em()->getRepository(Contractor::class)->count([]));
    }

    /**
     * @param class-string<AccountOperation|GoldPrice|CurrencyExchangeRate> $entityClass
     */
    #[DataProvider('validPostDates')]
    public function testPostPreservesValidDatesAtMidnight(
        string $uri,
        string $field,
        string $entityClass,
        string $submittedDate
    ): void
    {
        $this->requestJson('POST', $uri, $this->payloadFor($uri) + [$field => $submittedDate]);

        self::assertResponseStatusCodeSame(201);
        $data = self::decodeObject((string) $this->client->getResponse()->getContent());
        self::assertSame($submittedDate, self::stringAt($data, $field));
        $ids = '/api/transfers' === $uri
            ? [self::intAt($data, 'outOperationId'), self::intAt($data, 'inOperationId')]
            : [self::intAt($data, 'id')];

        $this->em()->clear();
        foreach ($ids as $id) {
            $stored = $this->em()->find($entityClass, $id);
            self::assertNotNull($stored);
            $date = match (true) {
                $stored instanceof AccountOperation => $stored->getOperationDate(),
                $stored instanceof GoldPrice => $stored->getPriceDate(),
                $stored instanceof CurrencyExchangeRate => $stored->getRateDate(),
            };
            self::assertSame($submittedDate.' 00:00:00.000000', $date->format('Y-m-d H:i:s.u'));
        }
    }

    #[DataProvider('validDates')]
    public function testPatchPreservesValidDates(string $date): void
    {
        $operation = $this->createOperation();
        $id = $operation->getId();

        $this->requestJson('PATCH', '/api/account_operations/'.$id, ['operationDate' => $date]);

        self::assertResponseIsSuccessful();
        $this->em()->clear();
        $updated = $this->em()->find(AccountOperation::class, $id);
        self::assertNotNull($updated);
        self::assertSame($date.' 00:00:00', $updated->getOperationDate()->format('Y-m-d H:i:s'));
        self::assertSame(1000, $updated->getAmount());
        self::assertSame('Original', $updated->getDescription());
    }

    /**
     * @return array<string, array{string, string, class-string<AccountOperation|GoldPrice|CurrencyExchangeRate>}>
     */
    public static function endpoints(): array
    {
        return [
            'operation' => ['/api/account_operations', 'operationDate', AccountOperation::class],
            'transfer' => ['/api/transfers', 'operationDate', AccountOperation::class],
            'gold price' => ['/api/gold_prices', 'priceDate', GoldPrice::class],
            'exchange rate' => ['/api/currency_exchange_rates', 'rateDate', CurrencyExchangeRate::class],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDates(): array
    {
        return [
            'nonexistent day' => ['2026-02-31'],
            'non-leap year' => ['2025-02-29'],
            'timestamp' => ['2026-01-15T12:00:00+01:00'],
            'relative date' => ['tomorrow'],
            'before supported range' => ['2024-12-31'],
            'after supported range' => ['2071-01-01'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validDates(): array
    {
        return [
            'first supported day' => ['2025-01-01'],
            'last supported day' => ['2070-12-31'],
            'leap day' => ['2028-02-29'],
        ];
    }

    /**
     * @return iterable<string, array{string, string, class-string<AccountOperation|GoldPrice|CurrencyExchangeRate>, string}>
     */
    public static function validPostDates(): iterable
    {
        foreach (self::endpoints() as $endpoint => [$uri, $field, $entityClass]) {
            foreach (self::validDates() as $case => [$date]) {
                yield $endpoint.' / '.$case => [$uri, $field, $entityClass, $date];
            }
        }
    }

    /**
     * @return iterable<string, array{string, string, class-string<AccountOperation|GoldPrice|CurrencyExchangeRate>, string}>
     */
    public static function invalidPostDates(): iterable
    {
        foreach (self::endpoints() as $endpoint => [$uri, $field, $entityClass]) {
            foreach (self::invalidDates() as $case => [$date]) {
                yield $endpoint.' / '.$case => [$uri, $field, $entityClass, $date];
            }
        }
    }

    /**
     * @return array<string, int|string>
     */
    private function payloadFor(string $uri): array
    {
        if ('/api/gold_prices' === $uri) {
            $form = $this->em()->getRepository(GoldForm::class)->findOneBy([]);
            self::assertNotNull($form);
            $asset = new GoldAsset($form, 10000, 'Date validation gold');
            $this->em()->persist($asset);
            $this->em()->flush();

            return ['goldAssetId' => $asset->getId(), 'pricePln' => '10000.00'];
        }

        $account = $this->account('Bank1-ROR-PLN');

        return match ($uri) {
            '/api/account_operations' => [
                'accountId' => $account->getId(),
                'contractorName' => 'Date validation contractor '.uniqid(),
                'amount' => '10.00',
            ],
            '/api/transfers' => [
                'fromAccountId' => $account->getId(),
                'toAccountId' => $this->account('Bank1-WAK-PLN')->getId(),
                'amountFrom' => '10.00',
            ],
            '/api/currency_exchange_rates' => [
                'bankId' => $account->getBank()->getId(),
                'currencyCode' => 'CHF',
                'rateToPln' => '4.3210',
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

    private function createOperation(): AccountOperation
    {
        $account = $this->account('Bank1-ROR-PLN');
        $contractor = new Contractor('Original contractor '.uniqid());
        $operation = new AccountOperation(
            $account,
            $contractor,
            new \DateTimeImmutable('2026-01-15'),
            1000,
            $account->getCurrency(),
            description: 'Original',
        );
        $this->em()->persist($contractor);
        $this->em()->persist($operation);
        $this->em()->flush();

        return $operation;
    }

    /**
     * @param array<string, int|string|bool> $payload
     */
    private function requestJson(string $method, string $uri, array $payload): void
    {
        $this->client->jsonRequest('POST', '/api/login_check', [
            'email' => 'test@example.com',
            'password' => 'test1234',
        ]);
        self::assertResponseIsSuccessful();
        $token = self::stringAt(self::decodeObject((string) $this->client->getResponse()->getContent()), 'token');

        $this->client->request(
            $method,
            $uri,
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'CONTENT_TYPE' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
            ],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
