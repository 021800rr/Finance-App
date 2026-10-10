<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use App\ApiResource\GoldAssetResource;
use App\Entity\Currency;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Entity\GoldPrice;
use App\Repository\GoldAssetRepository;
use App\State\GoldAssetProcessor;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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

    public function testProcessPostConvertsDecimalOuncesToScaledStorage(): void
    {
        $form = $this->createGoldForm();

        $result = $this->processor->process($this->resource($form, '1.5'), new Post());

        self::assertInstanceOf(GoldAssetResource::class, $result);
        self::assertSame('1.5000', $result->ounces);

        $stored = $this->goldAssetRepository->find($result->id);
        self::assertInstanceOf(GoldAsset::class, $stored);
        self::assertSame(15000, $stored->getOunces());
    }

    public function testProcessPostAcceptsSmallestAndLargestQuantity(): void
    {
        $form = $this->createGoldForm();

        $smallest = $this->processor->process($this->resource($form, '0.0001'), new Post());
        $largest = $this->processor->process($this->resource($form, '999.9999'), new Post());

        self::assertInstanceOf(GoldAssetResource::class, $smallest);
        self::assertInstanceOf(GoldAssetResource::class, $largest);
        self::assertSame('0.0001', $smallest->ounces);
        self::assertSame('999.9999', $largest->ounces);
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function invalidOuncesProvider(): iterable
    {
        yield 'null' => [null];
        yield 'blank' => ['  '];
        yield 'not a number' => ['abc'];
        yield 'negative' => ['-1.0'];
        yield 'comma separator' => ['1,5'];
        yield 'zero' => ['0'];
        yield 'below minimum after rounding' => ['0.00004'];
        yield 'above maximum' => ['1000'];
    }

    #[DataProvider('invalidOuncesProvider')]
    public function testProcessPostRejectsInvalidOunces(?string $ounces): void
    {
        $form = $this->createGoldForm();

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor->process($this->resource($form, $ounces), new Post());
    }

    private function createGoldForm(): GoldForm
    {
        $form = new GoldForm('Test Form ' . uniqid());
        $this->em()->persist($form);
        $this->em()->flush();

        return $form;
    }

    private function resource(GoldForm $form, ?string $ounces): GoldAssetResource
    {
        $resource = new GoldAssetResource();
        $resource->formId = $form->getId();
        $resource->description = 'Asset ' . uniqid();
        $resource->ounces = $ounces;

        return $resource;
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
