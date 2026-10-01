<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Form\GoldPriceType;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class GoldPriceTypeDefaultDateTest extends KernelTestCase
{
    use DatabaseTestTrait;

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
        $form = new GoldForm('Test Form ' . uniqid());
        $this->em()->persist($form);

        $goldAsset = new GoldAsset($form, 1, 'Test Asset ' . uniqid());
        $this->em()->persist($goldAsset);
        $this->em()->flush();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get('form.factory');

        $goldPriceForm = $formFactory->create(GoldPriceType::class, null, ['csrf_protection' => false]);

        $priceDate = $goldPriceForm->get('priceDate')->getData();

        self::assertInstanceOf(DateTimeImmutable::class, $priceDate);
        self::assertSame(new DateTimeImmutable()->format('Y-m-d'), $priceDate->format('Y-m-d'));
    }
}
