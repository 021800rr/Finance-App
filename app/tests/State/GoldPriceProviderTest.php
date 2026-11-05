<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\ApiResource\GoldPriceResource;
use App\State\GoldPriceProvider;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GoldPriceProviderTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

    private GoldPriceProvider $provider;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var GoldPriceProvider $provider */
        $provider = self::getContainer()->get(GoldPriceProvider::class);
        $this->provider = $provider;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProvideReturnsResourceForExistingId(): void
    {
        $goldAsset = $this->createGoldAsset();
        $goldPrice = $this->createGoldPrice($goldAsset, new \DateTimeImmutable('2026-01-01'), 100000);

        $result = $this->provider->provide(new Get(), ['id' => $goldPrice->getId()]);

        self::assertInstanceOf(GoldPriceResource::class, $result);
        self::assertSame($goldPrice->getId(), $result->id);
        self::assertSame($goldAsset->getId(), $result->goldAssetId);
    }

    public function testProvideThrowsNotFoundForNonExistentId(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Gold price entry not found.');

        $this->provider->provide(new Get(), ['id' => 999999999]);
    }

    public function testProvideCollectionOrdersByPriceDateDescending(): void
    {
        $goldAsset = $this->createGoldAsset();
        $older = $this->createGoldPrice($goldAsset, new \DateTimeImmutable('2026-01-01'), 100000);
        $newer = $this->createGoldPrice($goldAsset, new \DateTimeImmutable('2026-06-01'), 110000);

        /** @var list<GoldPriceResource> $result */
        $result = $this->provider->provide(new GetCollection());

        $ids = array_map(static fn (GoldPriceResource $r): ?int => $r->id, $result);
        $positionOfOlder = array_search($older->getId(), $ids, true);
        $positionOfNewer = array_search($newer->getId(), $ids, true);

        self::assertIsInt($positionOfOlder);
        self::assertIsInt($positionOfNewer);
        self::assertLessThan($positionOfOlder, $positionOfNewer);
    }
}
