<?php

declare(strict_types=1);

namespace App\Tests\Form;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Validator\Constraints\Range;

#[AllowMockObjectsWithoutExpectations]
final class GoldAssetTypeTest extends ValidatedTypeTestCase
{
    public function testOuncesRejectsZero(): void
    {
        $form = $this->factory->create(NumberType::class, null, [
            'constraints' => [new Range(min: 0.0001, max: 1000)],
        ]);
        $form->submit('0');

        self::assertFalse($form->isValid());
    }

    public function testOuncesRejectsValueAboveUpperBound(): void
    {
        $form = $this->factory->create(NumberType::class, null, [
            'constraints' => [new Range(min: 0.0001, max: 1000)],
        ]);
        $form->submit('1500');

        self::assertFalse($form->isValid());
    }

    public function testOuncesAcceptsValidValue(): void
    {
        $form = $this->factory->create(NumberType::class, null, [
            'constraints' => [new Range(min: 0.0001, max: 1000)],
        ]);
        $form->submit('1.5');

        self::assertTrue($form->isValid());
    }
}
