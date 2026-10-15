<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\Tests\DatabaseTestTrait;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CurrencyExchangeRateHistoryApiTest extends WebTestCase
{
    use DatabaseTestTrait;

    private const string VALID_EMAIL = 'test@example.com';
    private const string VALID_PASSWORD = 'test1234';

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

    public function testHistoryEndpointReturnsExactlyTheStoredIdBankIdCurrencyCodeAndRatePairs(): void
    {
        $bankA = new Bank('History Test Bank A '.uniqid());
        $bankB = new Bank('History Test Bank B '.uniqid());
        $this->em()->persist($bankA);
        $this->em()->persist($bankB);

        $currencyRepository = $this->em()->getRepository(Currency::class);
        $chf = $currencyRepository->find('CHF');
        if (null === $chf) {
            $chf = new Currency('CHF');
            $this->em()->persist($chf);
        }

        // Two distinct banks/amounts, on purpose: this test exists to catch exactly the kind of bug
        // where id/bankId/currencyCode/rate get mismatched or swapped between rows during mapping.
        $rateOne = new CurrencyExchangeRate($bankA, $chf, new \DateTimeImmutable('2026-01-05'), 43210);
        $rateTwo = new CurrencyExchangeRate($bankB, $chf, new \DateTimeImmutable('2026-02-10'), 43999);
        $this->em()->persist($rateOne);
        $this->em()->persist($rateTwo);
        $this->em()->flush();

        $expected = [
            [
                'id' => $rateOne->getId(),
                'bankId' => $bankA->getId(),
                'currencyCode' => 'CHF',
                'rateDate' => '2026-01-05',
                'rateToPln' => $rateOne->getRateToPlnAsMoney()->toDecimalString(),
            ],
            [
                'id' => $rateTwo->getId(),
                'bankId' => $bankB->getId(),
                'currencyCode' => 'CHF',
                'rateDate' => '2026-02-10',
                'rateToPln' => $rateTwo->getRateToPlnAsMoney()->toDecimalString(),
            ],
        ];

        $this->client->request('GET', '/api/currency_exchange_rates/history', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
        ]);

        self::assertResponseIsSuccessful();

        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertArrayHasKey('member', $data);
        self::assertIsArray($data['member']);

        /** @var array<int, array{id: int, bankId: int, currencyCode: string, rateDate: string, rateToPln: string}> $byId */
        $byId = [];
        foreach ($data['member'] as $item) {
            self::assertIsArray($item);
            /** @var int $id */
            $id = $item['id'];
            $byId[$id] = [
                'id' => $item['id'],
                'bankId' => $item['bankId'],
                'currencyCode' => $item['currencyCode'],
                'rateDate' => $item['rateDate'],
                'rateToPln' => $item['rateToPln'],
            ];
        }

        foreach ($expected as $expectedRow) {
            self::assertArrayHasKey($expectedRow['id'], $byId, 'Response is missing rate id '.$expectedRow['id']);
            self::assertSame($expectedRow, $byId[$expectedRow['id']]);
        }
    }

    private function obtainJwtToken(): string
    {
        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => self::VALID_EMAIL, 'password' => self::VALID_PASSWORD], \JSON_THROW_ON_ERROR),
        );

        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        if (!is_array($data) || !isset($data['token']) || !is_string($data['token'])) {
            throw new RuntimeException('Could not obtain a JWT token in the test.');
        }

        return $data['token'];
    }
}
