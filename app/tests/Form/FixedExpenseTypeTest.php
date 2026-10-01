<?php

declare(strict_types=1);

namespace App\Tests\Form;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Validator\Constraints\Range;

#[AllowMockObjectsWithoutExpectations]
final class FixedExpenseTypeTest extends ValidatedTypeTestCase
{
    public function testAmountRejectsPositiveValue(): void
    {
        $form = $this->factory->create(NumberType::class, null, [
            'constraints' => [new Range(min: -1000000, max: -0.01)],
        ]);
        $form->submit('100');

        self::assertFalse($form->isValid());
    }

    public function testAmountRejectsZero(): void
    {
        $form = $this->factory->create(NumberType::class, null, [
            'constraints' => [new Range(min: -1000000, max: -0.01)],
        ]);
        $form->submit('0');

        self::assertFalse($form->isValid());
    }

    public function testAmountRejectsValueBelowLowerBound(): void
    {
        $form = $this->factory->create(NumberType::class, null, [
            'constraints' => [new Range(min: -1000000, max: -0.01)],
        ]);
        $form->submit('-9999999');

        self::assertFalse($form->isValid());
    }

    public function testAmountAcceptsValidNegativeValue(): void
    {
        $form = $this->factory->create(NumberType::class, null, [
            'constraints' => [new Range(min: -1000000, max: -0.01)],
        ]);
        $form->submit('-150.50');

        self::assertTrue($form->isValid());
    }
}
