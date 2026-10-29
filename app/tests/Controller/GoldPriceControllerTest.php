<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Constant\Currencies;
use App\Entity\Currency;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Entity\GoldPrice;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GoldPriceControllerTest extends WebTestCase
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
        $this->client->request('GET', '/gold-prices');

        self::assertResponseIsSuccessful();
    }

    public function testIndexProvidesSeparateChartSeriesForAssetsWithTheSameDescription(): void
    {
        $first = $this->createPriceInDatabase();
        $second = $this->createPriceInDatabase(860050);

        $crawler = $this->client->request('GET', '/gold-prices');

        self::assertResponseIsSuccessful();
        $json = $crawler->filter('#goldPricesChart')->attr('data-chart');
        self::assertNotNull($json);
        $series = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([
            [
                'assetId' => $first->getGoldAsset()->getId(),
                'label' => 'Test',
                'prices' => [$first->getPriceDate()->format('Y-m-d') => 8000],
            ],
            [
                'assetId' => $second->getGoldAsset()->getId(),
                'label' => 'Test',
                'prices' => [$second->getPriceDate()->format('Y-m-d') => 8600.5],
            ],
        ], $series);
    }

    public function testIndexSubmittingWithValidDataCreatesPriceAndRedirects(): void
    {
        $asset = $this->createAssetInDatabase();

        $crawler = $this->client->request('GET', '/gold-prices');
        $form = $crawler->filter('#gold_price_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'gold_price[goldAsset]' => (string) $asset->getId(),
            'gold_price[priceDate]' => new DateTimeImmutable()->format('Y-m-d'),
            'gold_price[pricePln]' => '8000',
        ]);

        self::assertResponseRedirects('/gold-prices');

        $prices = $this->em()->getRepository(GoldPrice::class)->findBy(['goldAsset' => $asset]);

        self::assertCount(1, $prices);
        self::assertSame(800000, $prices[0]->getPricePln());
    }

    public function testIndexSubmittingWithNonPositivePriceShowsValidationError(): void
    {
        $asset = $this->createAssetInDatabase();

        $crawler = $this->client->request('GET', '/gold-prices');
        $form = $crawler->filter('#gold_price_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'gold_price[goldAsset]' => (string) $asset->getId(),
            'gold_price[priceDate]' => new DateTimeImmutable()->format('Y-m-d'),
            'gold_price[pricePln]' => '0',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#gold_price_form .invalid-feedback.d-block');
    }

    /**
     * @param array<string, string> $asset
     */
    #[DataProvider('blankAssets')]
    public function testIndexWithBlankAssetShowsFieldErrorWithoutSaving(array $asset): void
    {
        $countBefore = $this->em()->getRepository(GoldPrice::class)->count([]);
        $crawler = $this->client->request('GET', '/gold-prices');
        $token = $crawler->filter('#gold_price_form input[name="gold_price[_token]"]')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/gold-prices', [
            'gold_price' => [
                '_token' => $token,
                'priceDate' => new DateTimeImmutable()->format('Y-m-d'),
                'pricePln' => '8000',
            ] + $asset,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#gold_price_goldAsset.is-invalid');
        self::assertSelectorTextContains(
            '#gold_price_goldAsset + .invalid-feedback.d-block',
            'Please select an asset.',
        );
        self::assertSame($countBefore, $this->em()->getRepository(GoldPrice::class)->count([]));
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function blankAssets(): array
    {
        return [
            'empty' => [['goldAsset' => '']],
            'missing' => [[]],
        ];
    }

    #[DataProvider('unavailableAssetIds')]
    public function testIndexWithUnavailableAssetShowsFieldErrorWithoutSaving(string $assetId): void
    {
        $countBefore = $this->em()->getRepository(GoldPrice::class)->count([]);
        $crawler = $this->client->request('GET', '/gold-prices');
        $token = $crawler->filter('#gold_price_form input[name="gold_price[_token]"]')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/gold-prices', [
            'gold_price' => [
                '_token' => $token,
                'goldAsset' => $assetId,
                'priceDate' => new DateTimeImmutable()->format('Y-m-d'),
                'pricePln' => '8000',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#gold_price_goldAsset.is-invalid');
        self::assertSelectorExists('#gold_price_goldAsset + .invalid-feedback.d-block');
        self::assertSame($countBefore, $this->em()->getRepository(GoldPrice::class)->count([]));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unavailableAssetIds(): array
    {
        return [
            'above SQL integer' => ['2147483648'],
            'huge' => [str_repeat('9', 33)],
        ];
    }

    public function testDeleteReturnsNotFoundForNonNumericId(): void
    {
        $this->client->request('POST', '/gold-prices/alamakota/delete');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteRejectsInvalidCsrfToken(): void
    {
        $price = $this->createPriceInDatabase();

        $this->client->request('POST', '/gold-prices/' . $price->getId() . '/delete', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteRemovesPriceWithValidCsrfToken(): void
    {
        $price = $this->createPriceInDatabase();
        $priceId = $price->getId();

        $crawler = $this->client->request('GET', '/gold-prices');
        $csrfToken = $crawler->filter('form[action*="' . $priceId . '/delete"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/gold-prices/' . $priceId . '/delete', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/gold-prices');

        $deleted = $this->em()->find(GoldPrice::class, $priceId);

        self::assertNull($deleted);
    }

    private function createAssetInDatabase(): GoldAsset
    {
        $goldFormRepository = $this->em()->getRepository(GoldForm::class);
        $goldForm = $goldFormRepository->findOneBy(['name' => 'sztabka']);
        if (null === $goldForm) {
            $goldForm = new GoldForm('sztabka');
            $this->em()->persist($goldForm);
        }

        $asset = new GoldAsset($goldForm, 10000, 'Test');
        $this->em()->persist($asset);
        $this->em()->flush();

        return $asset;
    }

    private function createPriceInDatabase(int $priceMinorUnits = 800000): GoldPrice
    {
        $asset = $this->createAssetInDatabase();

        $pln = $this->em()->getRepository(Currency::class)->find(Currencies::SETTLEMENT_CURRENCY_CODE);
        self::assertNotNull($pln, 'PLN currency must exist in fixtures.');

        $price = new GoldPrice($asset, new DateTimeImmutable(), $priceMinorUnits, $pln);
        $this->em()->persist($price);
        $this->em()->flush();

        return $price;
    }
}
