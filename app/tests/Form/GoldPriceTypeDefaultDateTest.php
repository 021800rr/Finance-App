<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Form\GoldPriceType;
use App\Tests\DatabaseTestTrait;
use App\Tests\TestDataTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class GoldPriceTypeDefaultDateTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use TestDataTrait;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testPriceDateDefaultsToToday(): void
    {
        $this->createGoldAsset(ounces: 1, description: 'Test Asset '.uniqid());

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get('form.factory');

        $goldPriceForm = $formFactory->create(GoldPriceType::class, null, ['csrf_protection' => false]);

        $priceDate = $goldPriceForm->get('priceDate')->getData();

        self::assertInstanceOf(\DateTimeImmutable::class, $priceDate);
        self::assertSame(new \DateTimeImmutable()->format('Y-m-d'), $priceDate->format('Y-m-d'));
    }
}
