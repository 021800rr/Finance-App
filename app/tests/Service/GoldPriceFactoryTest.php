<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Service\GoldPriceFactory;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class GoldPriceFactoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private GoldPriceFactory $factory;
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        $container = self::getContainer();

        /** @var GoldPriceFactory $factory */
        $factory = $container->get(GoldPriceFactory::class);
        $this->factory = $factory;

        /** @var FormFactoryInterface $formFactory */
        $formFactory = $container->get('form.factory');
        $this->formFactory = $formFactory;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testCreateFromFormBuildsPriceWithCorrectMinorUnits(): void
    {
        $asset = $this->createGoldAsset();
        $date = new DateTimeImmutable('2026-01-15');

        $form = $this->buildForm($asset, $date, 16800.99);

        $price = $this->factory->createFromForm($form);

        self::assertSame($asset->getId(), $price->getGoldAsset()->getId());
        self::assertSame('PLN', $price->getCurrency()->getCode());
        self::assertSame(1680099, $price->getPricePln());
        self::assertSame('2026-01-15', $price->getPriceDate()->format('Y-m-d'));
    }

    public function testCreateFromFormThrowsWhenGoldAssetIsMissing(): void
    {
        $form = $this->buildForm(null, new DateTimeImmutable(), 16800.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Form data is incomplete or has unexpected types.');

        $this->factory->createFromForm($form);
    }

    public function testCreateFromFormThrowsWhenPriceDateIsMissing(): void
    {
        $asset = $this->createGoldAsset();
        $form = $this->buildForm($asset, null, 16800.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Form data is incomplete or has unexpected types.');

        $this->factory->createFromForm($form);
    }

    public function testCreateFromFormDelegatesNonPositivePriceRejectionToEntity(): void
    {
        $asset = $this->createGoldAsset();
        $form = $this->buildForm($asset, new DateTimeImmutable(), 0.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Price must be between 1 and 99999999.');

        $this->factory->createFromForm($form);
    }

    public function testCreateFromFormDelegatesOutOfRangePriceRejectionToEntity(): void
    {
        $asset = $this->createGoldAsset();
        $form = $this->buildForm($asset, new DateTimeImmutable(), 2000000.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Price must be between 1 and 99999999.');

        $this->factory->createFromForm($form);
    }

    private function buildForm(?GoldAsset $goldAsset, ?DateTimeImmutable $priceDate, float $price): FormInterface
    {
        $form = $this->formFactory->createNamed('', FormType::class, null, ['csrf_protection' => false]);
        $form->add('goldAsset', HiddenType::class, ['mapped' => false]);
        $form->add('priceDate', HiddenType::class, ['mapped' => false]);
        $form->add('pricePln', MoneyType::class, ['mapped' => false, 'currency' => false, 'input' => 'string']);

        $form->get('goldAsset')->setData($goldAsset);
        $form->get('priceDate')->setData($priceDate);
        $form->get('pricePln')->setData((string) $price);

        return $form;
    }

    private function createGoldAsset(): GoldAsset
    {
        $goldForm = new GoldForm('Test Form ' . uniqid());
        $this->em()->persist($goldForm);

        $asset = new GoldAsset($goldForm, 10000, 'Test');
        $this->em()->persist($asset);
        $this->em()->flush();

        return $asset;
    }
}
