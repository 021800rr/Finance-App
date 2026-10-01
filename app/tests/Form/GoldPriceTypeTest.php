<?php

declare(strict_types=1);

namespace App\Tests\Form;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Validator\Constraints\Range;

#[AllowMockObjectsWithoutExpectations]
final class GoldPriceTypeTest extends ValidatedTypeTestCase
{
    public function testPricePlnRejectsZero(): void
    {
        $form = $this->factory->create(NumberType::class, null, [
            'constraints' => [new Range(min: 0.01, max: 1000000)],
        ]);
        $form->submit('0');

        self::assertFalse($form->isValid());
    }

    public function testPricePlnRejectsValueAboveUpperBound(): void
    {
        $form = $this->factory->create(NumberType::class, null, [
            'constraints' => [new Range(min: 0.01, max: 1000000)],
        ]);
        $form->submit('2000000');

        self::assertFalse($form->isValid());
    }

    public function testPricePlnAcceptsValidValue(): void
    {
        $form = $this->factory->create(NumberType::class, null, [
            'constraints' => [new Range(min: 0.01, max: 1000000)],
        ]);
        $form->submit('16800.99');

        self::assertTrue($form->isValid());
    }
}
