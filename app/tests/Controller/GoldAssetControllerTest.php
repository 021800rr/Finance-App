<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GoldAssetControllerTest extends WebTestCase
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
        $this->client->request('GET', '/gold-assets');

        self::assertResponseIsSuccessful();
    }

    public function testIndexSubmittingWithValidDataCreatesAssetAndRedirects(): void
    {
        $goldForm = $this->getOrCreateGoldForm('sztabka');

        $crawler = $this->client->request('GET', '/gold-assets');
        $form = $crawler->selectButton('Add')->form();

        $this->client->submit($form, [
            'gold_asset[form]' => (string) $goldForm->getId(),
            'gold_asset[ounces]' => '1.5',
            'gold_asset[description]' => 'Test sztabka ' . uniqid(),
        ]);

        self::assertResponseRedirects('/gold-assets');

        $assets = $this->em()->getRepository(GoldAsset::class)->findBy(['form' => $goldForm]);

        self::assertNotEmpty($assets);
        $lastAsset = end($assets);
        self::assertInstanceOf(GoldAsset::class, $lastAsset);
        self::assertSame(15000, $lastAsset->getOunces());
    }

    public function testIndexSubmittingWithNonPositiveOuncesShowsValidationError(): void
    {
        $goldForm = $this->getOrCreateGoldForm('sztabka');

        $crawler = $this->client->request('GET', '/gold-assets');
        $form = $crawler->filter('#gold_asset_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'gold_asset[form]' => (string) $goldForm->getId(),
            'gold_asset[ounces]' => '0',
            'gold_asset[description]' => 'Test',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#gold_asset_form .invalid-feedback.d-block');
    }

    public function testEditReturnsNotFoundForNonNumericId(): void
    {
        $this->client->request('GET', '/gold-assets/alamakota/edit');

        self::assertResponseStatusCodeSame(404);
    }

    public function testEditReturnsNotFoundForNonExistentAsset(): void
    {
        $this->client->request('GET', '/gold-assets/999999999/edit');

        self::assertResponseStatusCodeSame(404);
    }

    public function testEditUpdatesAssetAndRedirects(): void
    {
        $asset = $this->createAssetInDatabase();
        $newGoldForm = $this->getOrCreateGoldForm('moneta');

        $crawler = $this->client->request('GET', '/gold-assets/' . $asset->getId() . '/edit');
        $form = $crawler->filter('#gold_asset_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'gold_asset[form]' => (string) $newGoldForm->getId(),
            'gold_asset[ounces]' => '2.25',
            'gold_asset[description]' => 'Updated description ' . uniqid(),
        ]);

        self::assertResponseRedirects('/gold-assets');

        $updated = $this->em()->find(GoldAsset::class, $asset->getId());

        self::assertNotNull($updated);
        self::assertSame(22500, $updated->getOunces());
        self::assertSame($newGoldForm->getId(), $updated->getForm()->getId());
    }

    public function testDeleteReturnsNotFoundForNonNumericId(): void
    {
        $this->client->request('POST', '/gold-assets/alamakota/delete');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteRejectsInvalidCsrfToken(): void
    {
        $asset = $this->createAssetInDatabase();

        $this->client->request('POST', '/gold-assets/' . $asset->getId() . '/delete', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteRemovesAssetWithValidCsrfToken(): void
    {
        $asset = $this->createAssetInDatabase();
        $assetId = $asset->getId();

        $crawler = $this->client->request('GET', '/gold-assets');
        $csrfToken = $crawler->filter('form[action*="' . $assetId . '/delete"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/gold-assets/' . $assetId . '/delete', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/gold-assets');

        $deleted = $this->em()->find(GoldAsset::class, $assetId);

        self::assertNull($deleted);
    }

    private function getOrCreateGoldForm(string $name): GoldForm
    {
        $goldFormRepository = $this->em()->getRepository(GoldForm::class);
        $goldForm = $goldFormRepository->findOneBy(['name' => $name]);
        if (null === $goldForm) {
            $goldForm = new GoldForm($name);
            $this->em()->persist($goldForm);
            $this->em()->flush();
        }

        return $goldForm;
    }

    private function createAssetInDatabase(): GoldAsset
    {
        $goldForm = $this->getOrCreateGoldForm('moneta');

        $asset = new GoldAsset($goldForm, 10000, 'Test');
        $this->em()->persist($asset);
        $this->em()->flush();

        return $asset;
    }
}
