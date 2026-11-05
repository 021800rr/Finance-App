<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\GoldAsset;
use App\Repository\GoldAssetRepository;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GoldAssetRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

    private GoldAssetRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var GoldAssetRepository $repository */
        $repository = $this->em()->getRepository(GoldAsset::class);
        $this->repository = $repository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testFindAllOrderedSortsByIdAscending(): void
    {
        $first = $this->createGoldAsset(description: 'First');
        $second = $this->createGoldAsset(description: 'Second');
        $third = $this->createGoldAsset(description: 'Third');

        $result = $this->repository->findAllOrdered();

        self::assertSame($first->getId(), $result[0]->getId());
        self::assertSame($second->getId(), $result[1]->getId());
        self::assertSame($third->getId(), $result[2]->getId());
    }

    public function testFindAllOrderedReturnsEmptyArrayWhenNoAssets(): void
    {
        $result = $this->repository->findAllOrdered();

        self::assertSame([], $result);
    }

    public function testFindAllOrderedIncludesForm(): void
    {
        $asset = $this->createGoldAsset(description: 'Test Description');

        $result = $this->repository->findAllOrdered();

        self::assertSame($asset->getForm()->getId(), $result[0]->getForm()->getId());
        self::assertSame($asset->getForm()->getName(), $result[0]->getForm()->getName());
    }
}
