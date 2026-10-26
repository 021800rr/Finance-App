<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Bank;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\Tests\Controller\LogsInTrait;
use App\Tests\DatabaseTestTrait;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CurrencyExchangeRateHistoryApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use LogsInTrait;

    private const string VALID_EMAIL = 'test@example.com';
    private const string VALID_PASSWORD = 'test1234';

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

    public function testHistoryReturnsDailyWinnersMatchingTheWebChart(): void
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

        // The daily winner has a lower rate but a higher ID; selection must match the web chart.
        $rateOne = new CurrencyExchangeRate($bankA, $chf, new \DateTimeImmutable('2026-01-05'), 47000);
        $rateTwo = new CurrencyExchangeRate($bankB, $chf, new \DateTimeImmutable('2026-02-10'), 43999);
        $sameDayRate = new CurrencyExchangeRate($bankA, $chf, new \DateTimeImmutable('2026-01-05'), 45000);
        $this->em()->persist($rateOne);
        $this->em()->persist($rateTwo);
        $this->em()->persist($sameDayRate);
        $this->em()->flush();

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

        $this->client->request('GET', '/api/currency_exchange_rates/history', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->obtainJwtToken(),
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
