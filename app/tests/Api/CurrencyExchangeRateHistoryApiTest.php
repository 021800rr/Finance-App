<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Controller\LogsInTrait;
use App\Tests\DatabaseTestTrait;
use App\Tests\JwtTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CurrencyExchangeRateHistoryApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use LogsInTrait;
    use TestDataTrait;
    use JwtTrait;

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

    public function testChartReturnsDailyWinnersMatchingTheWebChart(): void
    {
        $bankA = $this->createBank('History Test Bank A '.uniqid());
        $bankB = $this->createBank('History Test Bank B '.uniqid());
        $chf = $this->currency('CHF');

        // The daily winner has a lower rate but a higher ID; selection must match the web chart.
        $rateOne = $this->createRate($bankA, $chf, new \DateTimeImmutable('2026-01-05'), 47000);
        $rateTwo = $this->createRate($bankB, $chf, new \DateTimeImmutable('2026-02-10'), 43999);
        $sameDayRate = $this->createRate($bankA, $chf, new \DateTimeImmutable('2026-01-05'), 45000);

        $expected = [
            [
                'id' => $rateTwo->getId(),
                'bankId' => $bankB->getId(),
                'currencyCode' => 'CHF',
                'rateDate' => '2026-02-10',
                'rateToPln' => $rateTwo->getRateToPlnAsMoney()->toDecimalString(),
            ],
            [
                'id' => $sameDayRate->getId(),
                'bankId' => $bankA->getId(),
                'currencyCode' => 'CHF',
                'rateDate' => '2026-01-05',
                'rateToPln' => '4.5000',
            ],
        ];

        $this->client->request('GET', '/api/currency_exchange_rates/chart', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(),
        ]);

        self::assertResponseIsSuccessful();

        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertArrayHasKey('member', $data);
        self::assertIsArray($data['member']);

        $byId = [];
        foreach ($data['member'] as $item) {
            self::assertIsArray($item);
            self::assertIsInt($item['id']);
            self::assertIsInt($item['bankId']);
            self::assertIsString($item['currencyCode']);
            self::assertIsString($item['rateDate']);
            self::assertIsString($item['rateToPln']);
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
        self::assertArrayNotHasKey($rateOne->getId(), $byId);

        $bankNames = [$bankA->getId() => $bankA->getName(), $bankB->getId() => $bankB->getName()];
        $apiChart = [];
        foreach ($byId as $row) {
            $bankName = $bankNames[$row['bankId']] ?? null;
            if (null !== $bankName) {
                $apiChart[$bankName][$row['rateDate']] = (float) $row['rateToPln'];
            }
        }

        $crawler = $this->client->request('GET', '/rate');
        self::assertResponseIsSuccessful();
        $chartJson = $crawler->filter('#ratesChart')->attr('data-chart');
        self::assertIsString($chartJson);
        $webChart = json_decode($chartJson, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($webChart);
        foreach ($bankNames as $bankName) {
            self::assertSame($apiChart[$bankName], $webChart[$bankName]);
        }
    }
}
