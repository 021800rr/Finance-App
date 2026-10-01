<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Delete;
use App\Entity\Currency;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Entity\GoldPrice;
use App\Repository\GoldPriceRepository;
use App\State\GoldPriceProcessor;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GoldPriceProcessorTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private GoldPriceProcessor $processor;
    private GoldPriceRepository $goldPriceRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        /** @var GoldPriceProcessor $processor */
        $processor = self::getContainer()->get(GoldPriceProcessor::class);
        $this->processor = $processor;

        /** @var GoldPriceRepository $goldPriceRepository */
        $goldPriceRepository = self::getContainer()->get(GoldPriceRepository::class);
        $this->goldPriceRepository = $goldPriceRepository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testProcessDeletesExistingGoldPrice(): void
    {
        $goldPrice = $this->createGoldPrice($this->createGoldAsset());
        $id = $goldPrice->getId();

        $this->processor->process(null, new Delete(), ['id' => $id]);

        self::assertNull($this->goldPriceRepository->find($id));
    }

    public function testProcessThrowsNotFoundWhenGoldPriceDoesNotExist(): void
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

        $goldPrice = new GoldPrice($goldAsset, new \DateTimeImmutable(), 100000, $currency);
        $this->em()->persist($goldPrice);
        $this->em()->flush();

        return $goldPrice;
    }
}
