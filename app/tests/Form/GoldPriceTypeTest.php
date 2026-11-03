<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Form\GoldPriceType;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Submits the real GoldPriceType, so a changed or removed limit in the form fails here.
 */
final class GoldPriceTypeTest extends KernelTestCase
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

    #[DataProvider('validPrices')]
    public function testPriceWithinTheLimitsIsAccepted(string $submitted, string $expected): void
    {
        $field = $this->submit($submitted);

        self::assertTrue($field->isValid(), (string) $field->getErrors());
        self::assertSame($expected, $field->getData());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validPrices(): iterable
    {
        yield 'minimum' => ['0.01', '0.01'];
        yield 'maximum' => ['999999.99', '999999.99'];
        yield 'ordinary' => ['16800.99', '16800.99'];
    }

    #[DataProvider('invalidPrices')]
    public function testPriceOutsideTheLimitsIsRefusedWithItsMessage(string $submitted, string $message): void
    {
        $errors = iterator_to_array($this->submit($submitted)->getErrors());

        self::assertCount(1, $errors);
        self::assertInstanceOf(FormError::class, $errors[0]);
        self::assertSame($message, $errors[0]->getMessageTemplate());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidPrices(): iterable
    {
        yield 'zero' => ['0', 'gold_price.price_out_of_range'];
        yield 'above the maximum' => ['1000000', 'gold_price.price_out_of_range'];
        yield 'negative' => ['-1', 'gold_price.price_out_of_range'];
        yield 'empty' => ['', 'gold_price.price_not_blank'];
        yield 'not a number' => ['abc', 'common.numeric_invalid'];
    }

    private function submit(string $value): FormInterface
    {
        /** @var FormFactoryInterface $factory */
        $factory = self::getContainer()->get('form.factory');
        $form = $factory->create(GoldPriceType::class, null, ['csrf_protection' => false]);
        $form->submit(['pricePln' => $value]);

        return $form->get('pricePln');
    }
}
