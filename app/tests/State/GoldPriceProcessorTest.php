<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Delete;
use App\Repository\GoldPriceRepository;
use App\State\GoldPriceProcessor;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GoldPriceProcessorTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

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
}
