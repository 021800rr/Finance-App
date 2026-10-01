<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Bank;
use App\Entity\Currency;
use App\Service\CurrencyExchangeRateFactory;
use App\Tests\DatabaseTestTrait;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class CurrencyExchangeRateFactoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private CurrencyExchangeRateFactory $factory;
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        $container = CurrencyExchangeRateFactoryTest::getContainer();

        /** @var CurrencyExchangeRateFactory $factory */
        $factory = $container->get(CurrencyExchangeRateFactory::class);
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

    public function testCreateFromFormBuildsRateWithCorrectMinorUnits(): void
    {
        $bank = $this->createBank();
        $currency = $this->createCurrency('CHF');

        $form = $this->buildForm($bank, $currency, new DateTimeImmutable('2026-01-15'), 4.5);

        $rate = $this->factory->createFromForm($form);

        self::assertSame($bank->getId(), $rate->getBank()->getId());
        self::assertSame('CHF', $rate->getCurrency()->getCode());
        self::assertSame(45000, $rate->getRateToPln());
        self::assertSame('2026-01-15', $rate->getRateDate()->format('Y-m-d'));
    }

    public function testCreateFromFormThrowsWhenBankIsMissing(): void
    {
        $currency = $this->createCurrency('CHF');
        $form = $this->buildForm(null, $currency, new DateTimeImmutable(), 4.5);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Form data is incomplete or has unexpected types.');

        $this->factory->createFromForm($form);
    }

    public function testCreateFromFormThrowsWhenCurrencyIsMissing(): void
    {
        $bank = $this->createBank();
        $form = $this->buildForm($bank, null, new DateTimeImmutable(), 4.5);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Form data is incomplete or has unexpected types.');

        $this->factory->createFromForm($form);
    }

    public function testCreateFromFormThrowsWhenRateDateIsMissing(): void
    {
        $bank = $this->createBank();
        $currency = $this->createCurrency('CHF');
        $form = $this->buildForm($bank, $currency, null, 4.5);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Form data is incomplete or has unexpected types.');

        $this->factory->createFromForm($form);
    }

    public function testCreateFromFormHandlesHighPrecisionRate(): void
    {
        $bank = $this->createBank();
        $currency = $this->createCurrency('CHF');
        $form = $this->buildForm($bank, $currency, new DateTimeImmutable(), 4.1234);

        $rate = $this->factory->createFromForm($form);

        self::assertSame(41234, $rate->getRateToPln());
    }

    private function buildForm(?Bank $bank, ?Currency $currency, ?DateTimeImmutable $rateDate, float $rate): FormInterface
    {
        $form = $this->formFactory->createNamed('', FormType::class, null, ['csrf_protection' => false]);
        $form->add('bank', HiddenType::class, ['mapped' => false]);
        $form->add('currency', HiddenType::class, ['mapped' => false]);
        $form->add('rateDate', HiddenType::class, ['mapped' => false]);
        $form->add('rateToPln', MoneyType::class, [
            'mapped' => false,
            'currency' => false,
            'scale' => 4,
            'input' => 'string',
        ]);

        $form->get('bank')->setData($bank);
        $form->get('currency')->setData($currency);
        $form->get('rateDate')->setData($rateDate);
        $form->get('rateToPln')->setData((string) $rate);

        return $form;
    }

    private function createBank(): Bank
    {
        $bank = new Bank('Test Bank ' . uniqid());
        $this->em()->persist($bank);
        $this->em()->flush();

        return $bank;
    }

    private function createCurrency(string $code): Currency
    {
        $currency = $this->em()->getRepository(Currency::class)->find($code);
        if (null === $currency) {
            $currency = new Currency($code);
            $this->em()->persist($currency);
            $this->em()->flush();
        }

        return $currency;
    }
}
