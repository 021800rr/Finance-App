<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Form\FixedExpenseType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\FormError;

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

    /**
     * @param array<string, string|null> $amount
     */
    #[DataProvider('blankAmounts')]
    public function testBlankAmountShowsRequiredFieldError(array $amount): void
    {
        $form = $this->factory->create(FixedExpenseType::class);
        $form->submit(['day' => '10', 'contractor' => '1'] + $amount);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('amount')->getErrors());
        $error = $form->get('amount')->getErrors()[0];
        self::assertInstanceOf(FormError::class, $error);
        self::assertSame('fixed_expense.amount_not_blank', $error->getMessageTemplate());
    }

    /**
     * @return array<string, array{array<string, string|null>}>
     */
    public static function blankAmounts(): array
    {
        return [
            'empty' => [['amount' => '']],
            'spaces' => [['amount' => '   ']],
            'null' => [['amount' => null]],
            'missing' => [[]],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function testAmountRejectsInvalidValue(string $amount): void
    {
        $form = $this->factory->create(FixedExpenseType::class);
        $form->submit(['day' => '10', 'contractor' => '1', 'amount' => $amount]);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('amount')->getErrors());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidAmounts(): array
    {
        return [
            'positive' => ['100.00'],
            'zero' => ['0'],
            'below minimum' => ['-1000000.00'],
            'not numeric' => ['abc'],
        ];
    }

    #[DataProvider('validAmounts')]
    public function testAmountAcceptsValidNegativeValue(string $amount): void
    {
        $form = $this->factory->create(FixedExpenseType::class);
        $form->submit(['day' => '10', 'contractor' => '1', 'amount' => $amount]);

        self::assertTrue($form->isValid());
        self::assertSame($amount, $form->get('amount')->getData());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validAmounts(): array
    {
        return [
            'ordinary amount' => ['-150.50'],
            'minimum' => ['-999999.99'],
            'maximum' => ['-0.01'],
        ];
    }
}
