<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $description = 'Test sztabka '.uniqid();

        $crawler = $this->client->request('GET', '/gold-assets');
        $form = $crawler->selectButton('Add')->form();

        $this->client->submit($form, [
            'gold_asset[form]' => (string) $goldForm->getId(),
            'gold_asset[ounces]' => '1.5',
            'gold_asset[description]' => $description,
        ]);

        self::assertResponseRedirects('/gold-assets');

        $assets = $this->em()->getRepository(GoldAsset::class)->findBy(['form' => $goldForm]);

        self::assertNotEmpty($assets);
        $lastAsset = end($assets);
        self::assertInstanceOf(GoldAsset::class, $lastAsset);
        self::assertSame(15000, $lastAsset->getOunces());
        self::assertSame($description, $lastAsset->getDescription());
    }

    /**
     * @param array<string, string> $description
     */
    #[DataProvider('blankDescriptions')]
    public function testCreateWithBlankDescriptionShowsFieldErrorWithoutSaving(array $description): void
    {
        $goldForm = $this->getOrCreateGoldForm('sztabka');
        $countBefore = $this->em()->getRepository(GoldAsset::class)->count([]);
        $crawler = $this->client->request('GET', '/gold-assets');
        $token = $crawler->filter('#gold_asset_form input[name="gold_asset[_token]"]')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', '/gold-assets', [
            'gold_asset' => [
                '_token' => $token,
                'form' => (string) $goldForm->getId(),
                'ounces' => '1.5',
            ] + $description,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#gold_asset_description[required].is-invalid');
        self::assertSelectorTextContains(
            '#gold_asset_description + .invalid-feedback.d-block',
            'Please enter a description.',
        );
        self::assertInputValueSame('gold_asset[description]', '');
        self::assertInputValueSame('gold_asset[ounces]', '1.5');
        self::assertSame($countBefore, $this->em()->getRepository(GoldAsset::class)->count([]));
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
        $description = 'Updated description '.uniqid();

        $crawler = $this->client->request('GET', '/gold-assets/' . $asset->getId() . '/edit');
        $form = $crawler->filter('#gold_asset_form button[type="submit"]')->form();

        $this->client->submit($form, [
            'gold_asset[form]' => (string) $newGoldForm->getId(),
            'gold_asset[ounces]' => '2.25',
            'gold_asset[description]' => $description,
        ]);

        self::assertResponseRedirects('/gold-assets');

        $updated = $this->em()->find(GoldAsset::class, $asset->getId());

        self::assertNotNull($updated);
        self::assertSame(22500, $updated->getOunces());
        self::assertSame($newGoldForm->getId(), $updated->getForm()->getId());
        self::assertSame($description, $updated->getDescription());
    }

    /**
     * @param array<string, string> $description
     */
    #[DataProvider('blankDescriptions')]
    public function testEditWithBlankDescriptionShowsFieldErrorWithoutChangingAsset(array $description): void
    {
        $asset = $this->createAssetInDatabase();
        $id = $asset->getId();
        $originalFormId = $asset->getForm()->getId();
        $newGoldForm = $this->getOrCreateGoldForm('sztabka');
        $countBefore = $this->em()->getRepository(GoldAsset::class)->count([]);
        $uri = '/gold-assets/'.$id.'/edit';
        $crawler = $this->client->request('GET', $uri);
        $token = $crawler->filter('#gold_asset_form input[name="gold_asset[_token]"]')->attr('value');
        self::assertIsString($token);

        $this->client->request('POST', $uri, [
            'gold_asset' => [
                '_token' => $token,
                'form' => (string) $newGoldForm->getId(),
                'ounces' => '2.25',
            ] + $description,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#gold_asset_description[required].is-invalid');
        self::assertSelectorTextContains(
            '#gold_asset_description + .invalid-feedback.d-block',
            'Please enter a description.',
        );
        self::assertInputValueSame('gold_asset[description]', '');
        self::assertInputValueSame('gold_asset[ounces]', '2.25');
        $this->em()->clear();
        $unchanged = $this->em()->find(GoldAsset::class, $id);
        self::assertNotNull($unchanged);
        self::assertSame('Test', $unchanged->getDescription());
        self::assertSame(10000, $unchanged->getOunces());
        self::assertSame($originalFormId, $unchanged->getForm()->getId());
        self::assertSame($countBefore, $this->em()->getRepository(GoldAsset::class)->count([]));
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function blankDescriptions(): array
    {
        return [
            'empty' => [['description' => '']],
            'spaces' => [['description' => '   ']],
            'missing' => [[]],
        ];
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
