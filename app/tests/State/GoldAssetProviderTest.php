<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\ApiResource\GoldAssetResource;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\State\GoldAssetProvider;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GoldAssetProviderTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private GoldAssetProvider $provider;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var GoldAssetProvider $provider */
        $provider = self::getContainer()->get(GoldAssetProvider::class);
        $this->provider = $provider;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProvideReturnsResourceForExistingId(): void
    {
        $goldAsset = $this->createGoldAsset('First asset '.uniqid());

        $result = $this->provider->provide(new Get(), ['id' => $goldAsset->getId()]);

        self::assertInstanceOf(GoldAssetResource::class, $result);
        self::assertSame($goldAsset->getId(), $result->id);
        self::assertSame($goldAsset->getDescription(), $result->description);
        self::assertSame($goldAsset->getForm()->getName(), $result->formName);
    }

    public function testProvideReturnsOuncesAsDecimalString(): void
    {
        $form = new GoldForm('Test Form '.uniqid());
        $this->em()->persist($form);
        $goldAsset = new GoldAsset($form, 15000, 'Asset '.uniqid());
        $this->em()->persist($goldAsset);
        $this->em()->flush();

        $result = $this->provider->provide(new Get(), ['id' => $goldAsset->getId()]);

        self::assertInstanceOf(GoldAssetResource::class, $result);
        self::assertSame('1.5000', $result->ounces);
    }

    public function testProvideReturnsNullForNonExistentId(): void
    {
        $result = $this->provider->provide(new Get(), ['id' => 999999999]);

        self::assertNull($result);
    }

    public function testProvideCollectionOrdersByIdAscending(): void
    {
        $first = $this->createGoldAsset('First asset '.uniqid());
        $second = $this->createGoldAsset('Second asset '.uniqid());

        /** @var list<GoldAssetResource> $result */
        $result = $this->provider->provide(new GetCollection());

        $ids = array_map(static fn (GoldAssetResource $r): ?int => $r->id, $result);
        $positionOfFirst = array_search($first->getId(), $ids, true);
        $positionOfSecond = array_search($second->getId(), $ids, true);

        self::assertIsInt($positionOfFirst);
        self::assertIsInt($positionOfSecond);
        self::assertLessThan($positionOfSecond, $positionOfFirst);
    }

    private function createGoldAsset(string $description): GoldAsset
    {
        $form = new GoldForm('Test Form '.uniqid());
        $this->em()->persist($form);
        $this->em()->flush();

        $goldAsset = new GoldAsset($form, 1, $description);
        $this->em()->persist($goldAsset);
        $this->em()->flush();

        return $goldAsset;
    }
}
