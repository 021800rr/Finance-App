<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\ApiResource\GoldAssetResource;
use App\State\GoldAssetProvider;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GoldAssetProviderTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

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
        $goldAsset = $this->createGoldAsset(description: 'First asset '.uniqid());

        $result = $this->provider->provide(new Get(), ['id' => $goldAsset->getId()]);

        self::assertInstanceOf(GoldAssetResource::class, $result);
        self::assertSame($goldAsset->getId(), $result->id);
        self::assertSame($goldAsset->getDescription(), $result->description);
        self::assertSame($goldAsset->getForm()->getName(), $result->formName);
    }

    public function testProvideReturnsOuncesAsDecimalString(): void
    {
        $goldAsset = $this->createGoldAsset(ounces: 15000, description: 'Asset '.uniqid());

        $result = $this->provider->provide(new Get(), ['id' => $goldAsset->getId()]);

        self::assertInstanceOf(GoldAssetResource::class, $result);
        self::assertSame('1.5000', $result->ounces);
    }

    public function testProvideThrowsNotFoundForNonExistentId(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Gold asset not found.');

        $this->provider->provide(new Get(), ['id' => 999999999]);
    }

    public function testProvideCollectionOrdersByIdAscending(): void
    {
        $first = $this->createGoldAsset(description: 'First asset '.uniqid());
        $second = $this->createGoldAsset(description: 'Second asset '.uniqid());

        /** @var list<GoldAssetResource> $result */
        $result = $this->provider->provide(new GetCollection());

        $ids = array_map(static fn (GoldAssetResource $r): ?int => $r->id, $result);
        $positionOfFirst = array_search($first->getId(), $ids, true);
        $positionOfSecond = array_search($second->getId(), $ids, true);

        self::assertIsInt($positionOfFirst);
        self::assertIsInt($positionOfSecond);
        self::assertLessThan($positionOfSecond, $positionOfFirst);
    }
}
