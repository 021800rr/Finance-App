<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Form\FixedExpenseType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Validator\Constraints\Range;

#[AllowMockObjectsWithoutExpectations]
final class FixedExpenseTypeTest extends ValidatedTypeTestCase
{
    #[DataProvider('validDays')]
    public function testDayAcceptsRangeBoundaries(string $day): void
    {
        $form = $this->factory->create(FixedExpenseType::class);
        $form->submit(['day' => $day, 'amount' => '-10.00', 'contractor' => '1']);

        self::assertTrue($form->isValid());
        self::assertSame((int) $day, $form->get('day')->getData());
    }

    #[DataProvider('invalidDays')]
    public function testDayRejectsInvalidValues(string $day): void
    {
        $form = $this->factory->create(FixedExpenseType::class);
        $form->submit(['day' => $day, 'amount' => '-10.00', 'contractor' => '1']);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('day')->getErrors());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validDays(): array
    {
        return [
            'first day' => ['1'],
            'last allowed day' => ['28'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDays(): array
    {
        return [
            'below minimum' => ['0'],
            'above maximum' => ['29'],
            'empty day' => [''],
        ];
    }

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
