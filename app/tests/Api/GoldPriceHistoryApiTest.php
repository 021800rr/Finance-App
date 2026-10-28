<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Currency;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Entity\GoldPrice;
use App\Tests\Controller\LogsInTrait;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GoldPriceHistoryApiTest extends WebTestCase
{
    use DatabaseTestTrait;
    use LogsInTrait;
    use ReadsJsonTrait;

    private KernelBrowser $client;
    private ?string $token = null;

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

    public function testHistoryReturnsDailyWinnersMatchingTheWebChart(): void
    {
        $this->logIn();
        // Identical form, weight and description must still produce separate series by asset ID.
        $assetA = $this->createAsset();
        $assetB = $this->createAsset();
        $this->createPrice($assetA, '2026-10-26', 1100000);
        $latestA = $this->createPrice($assetA, '2026-10-26', 1000001);
        $this->createPrice($assetB, '2026-10-26', 1200000);
        $latestB = $this->createPrice($assetB, '2026-10-26', 1300020);
        $historical = $this->createPrice($assetA, '2026-10-25', 9000001);

        $rows = $this->getCollection('/api/gold_prices/history');

        self::assertSame(
            [$historical->getId(), $latestA->getId(), $latestB->getId()],
            array_column($rows, 'id'),
        );
        self::assertSame(['90000.01', '10000.01', '13000.20'], array_column($rows, 'pricePln'));
        self::assertSame(['PLN', 'PLN', 'PLN'], array_column($rows, 'currencyCode'));

        $apiSeries = [];
        foreach ($rows as $row) {
            $assetId = self::intAt($row, 'goldAssetId');
            $date = self::stringAt($row, 'priceDate');
            $apiSeries[$assetId][$date] = (float) self::stringAt($row, 'pricePln');
        }

        $crawler = $this->client->request('GET', '/gold-prices');
        self::assertResponseIsSuccessful();
        $chartJson = $crawler->filter('#goldPricesChart')->attr('data-chart');
        self::assertIsString($chartJson);
        $chart = json_decode($chartJson, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([
            [
                'assetId' => $assetA->getId(),
                'label' => $assetA->getDescription(),
                'prices' => $apiSeries[$assetA->getId()],
            ],
            [
                'assetId' => $assetB->getId(),
                'label' => $assetB->getDescription(),
                'prices' => $apiSeries[$assetB->getId()],
            ],
        ], $chart);
    }

    public function testListReturnsLatestTwentyEntriesWhileHistoryIncludesEveryDay(): void
    {
        $asset = $this->createAsset();
        $ids = [];
        for ($day = 1; $day <= 21; ++$day) {
            $date = sprintf('2026-01-%02d', $day);
            $ids[] = $this->createPrice($asset, $date, 100000 + $day)->getId();
        }
        $sameDayPrice = $this->createPrice($asset, '2026-01-21', 99000);

        $list = $this->getCollection('/api/gold_prices');
        $expectedListIds = array_slice(array_reverse([...$ids, $sameDayPrice->getId()]), 0, 20);
        self::assertSame($expectedListIds, array_column($list, 'id'));

        $history = $this->getCollection('/api/gold_prices/history');
        $expectedHistoryIds = [...array_slice($ids, 0, -1), $sameDayPrice->getId()];
        self::assertSame($expectedHistoryIds, array_column($history, 'id'));
        self::assertSame('990.00', self::stringAt($history[20], 'pricePln'));
    }

    public function testHistoryRequiresApiAuthentication(): void
    {
        $this->client->request('GET', '/api/gold_prices/history');

        self::assertResponseStatusCodeSame(401);
    }

    public function testOpenApiDocumentsBothCollectionsAndOrdersGoldPriceBeforeGoldAsset(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        self::assertResponseIsSuccessful();
        $document = self::decodeObject((string) $this->client->getResponse()->getContent());
        $paths = self::objectAt($document, 'paths');
        $list = self::objectAt($paths, '/api/gold_prices', 'get');
        $history = self::objectAt($paths, '/api/gold_prices/history', 'get');

        self::assertSame(['GoldPrice'], $list['tags']);
        self::assertSame(['GoldPrice'], $history['tags']);
        self::assertSame([['JWT' => []]], $list['security']);
        self::assertSame([['JWT' => []]], $history['security']);
        self::assertStringContainsString('20', self::stringAt($list, 'description'));
        self::assertStringContainsString('highest id per asset and date', self::stringAt($history, 'description'));

        $pricePaths = array_values(array_filter(
            array_keys($paths),
            static fn (string $path): bool => str_starts_with($path, '/api/gold_prices'),
        ));
        self::assertSame(['/api/gold_prices', '/api/gold_prices/history', '/api/gold_prices/{id}'], $pricePaths);

        $tags = array_map(
            static fn (array $tag): string => self::stringAt($tag, 'name'),
            self::rowsAt($document, 'tags'),
        );
        $pricePosition = array_search('GoldPrice', $tags, true);
        $assetPosition = array_search('GoldAsset', $tags, true);
        self::assertIsInt($pricePosition);
        self::assertIsInt($assetPosition);
        self::assertLessThan($assetPosition, $pricePosition);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getCollection(string $uri): array
    {
        $this->client->request('GET', $uri, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(),
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        self::assertResponseIsSuccessful();

        $data = self::decodeObject((string) $this->client->getResponse()->getContent());

        return self::rowsAt($data, 'member');
    }

    private function jwt(): string
    {
        if (null === $this->token) {
            $this->client->request(
                'POST',
                '/api/login_check',
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode(['email' => 'test@example.com', 'password' => 'test1234'], JSON_THROW_ON_ERROR),
            );
            self::assertResponseIsSuccessful();
            $data = self::decodeObject((string) $this->client->getResponse()->getContent());
            $this->token = self::stringAt($data, 'token');
        }

        return $this->token;
    }

    private function createAsset(): GoldAsset
    {
        $form = $this->em()->getRepository(GoldForm::class)->findOneBy(['name' => 'sztabka']);
        self::assertNotNull($form);

        $asset = new GoldAsset($form, 10000, 'Same description');
        $this->em()->persist($asset);
        $this->em()->flush();

        return $asset;
    }

    private function createPrice(GoldAsset $asset, string $date, int $minorUnits): GoldPrice
    {
        $currency = $this->em()->getRepository(Currency::class)->find('PLN');
        self::assertNotNull($currency);

        $price = new GoldPrice($asset, new DateTimeImmutable($date), $minorUnits, $currency);
        $this->em()->persist($price);
        $this->em()->flush();

        return $price;
    }
}
