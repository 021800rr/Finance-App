<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Form\CurrencyExchangeRateType;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Submits the real CurrencyExchangeRateType, so a changed or removed limit in the form fails here.
 */
final class CurrencyExchangeRateTypeTest extends KernelTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        $this->initEntityManager(self::bootKernel());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    #[DataProvider('validRates')]
    public function testRateWithinTheLimitsIsAccepted(string $submitted, string $expected): void
    {
        $field = $this->submit($submitted);

        self::assertTrue($field->isValid(), (string) $field->getErrors());
        self::assertSame($expected, $field->getData());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validRates(): iterable
    {
        yield 'minimum' => ['0.0001', '0.0001'];
        yield 'maximum' => ['99.9999', '99.9999'];
        yield 'ordinary' => ['4.5', '4.5000'];
    }

    #[DataProvider('invalidRates')]
    public function testRateOutsideTheLimitsIsRefusedWithItsMessage(string $submitted, string $message): void
    {
        $errors = iterator_to_array($this->submit($submitted)->getErrors());

        self::assertCount(1, $errors);
        self::assertInstanceOf(FormError::class, $errors[0]);
        self::assertSame($message, $errors[0]->getMessageTemplate());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidRates(): iterable
    {
        yield 'zero' => ['0', 'currency_exchange_rate.rate_out_of_range'];
        yield 'above the maximum' => ['100', 'currency_exchange_rate.rate_out_of_range'];
        yield 'negative' => ['-1', 'currency_exchange_rate.rate_out_of_range'];
        yield 'empty' => ['', 'currency_exchange_rate.rate_not_blank'];
        yield 'not a number' => ['abc', 'common.numeric_invalid'];
    }

    private function submit(string $value): FormInterface
    {
        /** @var FormFactoryInterface $factory */
        $factory = self::getContainer()->get('form.factory');
        $form = $factory->create(CurrencyExchangeRateType::class, null, ['csrf_protection' => false]);
        $form->submit(['rateToPln' => $value]);

        return $form->get('rateToPln');
    }
}
