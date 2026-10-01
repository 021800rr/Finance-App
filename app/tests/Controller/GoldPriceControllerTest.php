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

    private function createPriceInDatabase(): GoldPrice
    {
        $asset = $this->createAssetInDatabase();

        $pln = $this->em()->getRepository(Currency::class)->find(Currencies::SETTLEMENT_CURRENCY_CODE);
        self::assertNotNull($pln, 'PLN currency must exist in fixtures.');

        $price = new GoldPrice($asset, new DateTimeImmutable(), 800000, $pln);
        $this->em()->persist($price);
        $this->em()->flush();

        return $price;
    }
}
