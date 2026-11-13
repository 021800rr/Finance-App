<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use App\ApiResource\Input\GoldPriceInput;
use App\Entity\GoldAsset;
use App\Repository\GoldPriceRepository;
use App\State\GoldPriceProcessor;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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

    public function testProcessThrowsWhenDataIsNotGoldPriceInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->processor->process(new \stdClass(), new Post());
    }

    public function testProcessPostRejectsAMissingGoldAsset(): void
    {
        $input = $this->input($this->createGoldAsset(), '100.00');
        $input->goldAssetId = null;

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($input, new Post());
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function invalidPriceProvider(): iterable
    {
        yield 'null' => [null];
        yield 'blank' => ['  '];
        yield 'not a number' => ['abc'];
        yield 'zero' => ['0.00'];
        yield 'above maximum' => ['1000000.00'];
    }

    #[DataProvider('invalidPriceProvider')]
    public function testProcessPostRejectsInvalidPrice(?string $pricePln): void
    {
        $asset = $this->createGoldAsset();

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($this->input($asset, $pricePln), new Post());
    }

    private function input(GoldAsset $asset, ?string $pricePln): GoldPriceInput
    {
        $input = new GoldPriceInput();
        $input->goldAssetId = $asset->getId();
        $input->priceDate = '2026-11-12';
        $input->pricePln = $pricePln;

        return $input;
    }
}
