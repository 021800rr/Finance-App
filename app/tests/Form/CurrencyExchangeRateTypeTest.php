<?php

declare(strict_types=1);

namespace App\Tests\Form;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Validator\Constraints\Range;

#[AllowMockObjectsWithoutExpectations]
final class CurrencyExchangeRateTypeTest extends ValidatedTypeTestCase
{
    public function testRateToPlnRejectsZero(): void
    {
        $form = $this->factory->create(NumberType::class, null, [
            'constraints' => [new Range(min: 0.0001, max: 100)],
        ]);
        $form->submit('0');

        self::assertFalse($form->isValid());
    }

    public function testRateToPlnRejectsValueAboveUpperBound(): void
    {
        $form = $this->factory->create(NumberType::class, null, [
            'constraints' => [new Range(min: 0.0001, max: 100)],
        ]);
        $form->submit('150');

        self::assertFalse($form->isValid());
    }

    public function testRateToPlnAcceptsValidValue(): void
    {
        $form = $this->factory->create(NumberType::class, null, [
            'constraints' => [new Range(min: 0.0001, max: 100)],
        ]);
        $form->submit('4.5');

        self::assertTrue($form->isValid());
    }
}
