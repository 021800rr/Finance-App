<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Delete;
use App\Entity\Currency;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Entity\GoldPrice;
use App\Repository\GoldAssetRepository;
use App\State\GoldAssetProcessor;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GoldAssetProcessorTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private GoldAssetProcessor $processor;
    private GoldAssetRepository $goldAssetRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var GoldAssetProcessor $processor */
        $processor = self::getContainer()->get(GoldAssetProcessor::class);
        $this->processor = $processor;

        /** @var GoldAssetRepository $goldAssetRepository */
        $goldAssetRepository = self::getContainer()->get(GoldAssetRepository::class);
        $this->goldAssetRepository = $goldAssetRepository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProcessDeletesGoldAssetWithoutPrices(): void
    {
        $goldAsset = $this->createGoldAsset();
        $id = $goldAsset->getId();

        $this->processor->process(null, new Delete(), ['id' => $id]);

        self::assertNull($this->goldAssetRepository->find($id));
    }

    public function testProcessDeletesGoldAssetAndCascadesItsPriceHistory(): void
    {
        $goldAsset = $this->createGoldAsset();
        $this->createGoldPrice($goldAsset);
        $id = $goldAsset->getId();

        $this->processor->process(null, new Delete(), ['id' => $id]);

        self::assertNull($this->goldAssetRepository->find($id));

        /** @var string $count */
        $count = $this->em()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM gold_price WHERE gold_asset_id = :id',
            ['id' => $id],
        );
        self::assertSame(0, (int) $count);
    }

    public function testProcessThrowsNotFoundWhenGoldAssetDoesNotExist(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->processor->process(null, new Delete(), ['id' => 999999999]);
    }

    private function createGoldAsset(): GoldAsset
    {
        $form = new GoldForm('Test Form ' . uniqid());
        $this->em()->persist($form);
        $this->em()->flush();

        $goldAsset = new GoldAsset($form, 1, 'Test asset ' . uniqid());
        $this->em()->persist($goldAsset);
        $this->em()->flush();

        return $goldAsset;
    }

    private function createGoldPrice(GoldAsset $goldAsset): GoldPrice
    {
        $currencyRepository = $this->em()->getRepository(Currency::class);
        $currency = $currencyRepository->find('PLN');
        if (null === $currency) {
            $currency = new Currency('PLN');
            $this->em()->persist($currency);
        }

        $goldPrice = new GoldPrice($goldAsset, new DateTimeImmutable(), 100000, $currency);
        $this->em()->persist($goldPrice);
        $this->em()->flush();

        return $goldPrice;
    }
}
