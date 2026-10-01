<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Form\CurrencyExchangeRateType;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class CurrencyExchangeRateTypeDefaultDateTest extends KernelTestCase
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

    public function testRateDateDefaultsToToday(): void
    {
        /** @var FormFactoryInterface $formFactory */
        $formFactory = self::getContainer()->get('form.factory');

        $form = $formFactory->create(CurrencyExchangeRateType::class, null, ['csrf_protection' => false]);

        $rateDate = $form->get('rateDate')->getData();

        self::assertInstanceOf(DateTimeImmutable::class, $rateDate);
        self::assertSame(new DateTimeImmutable()->format('Y-m-d'), $rateDate->format('Y-m-d'));
    }
}
