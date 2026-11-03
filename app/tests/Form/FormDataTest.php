<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Form\FormData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;

final class FormDataTest extends TestCase
{
    public function testReadsEachKindOfValue(): void
    {
        $date = new \DateTimeImmutable('2026-01-15');
        $form = $this->form(['date' => $date, 'day' => 10, 'amount' => '-1.50', 'text' => '  Rent  ']);

        self::assertSame($date, FormData::date($form, 'date'));
        self::assertSame($date, FormData::entity($form, 'date', \DateTimeInterface::class));
        self::assertSame(10, FormData::integer($form, 'day'));
        self::assertSame('-1.50', FormData::decimal($form, 'amount'));
        self::assertSame('-1.50', FormData::optionalDecimal($form, 'amount'));
        self::assertSame('Rent', FormData::optionalText($form, 'text'));
    }

    public function testEmptyOptionalValuesAreNull(): void
    {
        $form = $this->form(['amount' => null, 'text' => '   ']);

        self::assertNull(FormData::optionalDecimal($form, 'amount'));
        self::assertNull(FormData::optionalText($form, 'text'));
    }

    /**
     * @return iterable<string, array{\Closure(FormInterface): mixed, mixed, string}>
     */
    public static function wrongValues(): iterable
    {
        $numeric = 'x must be a numeric value.';
        yield 'missing record' => [
            static fn (FormInterface $f) => FormData::entity($f, 'x', \stdClass::class),
            null,
            'x is required.',
        ];
        yield 'date as text' => [
            static fn (FormInterface $f) => FormData::date($f, 'x'),
            '2026-01-15',
            'x is required.',
        ];
        yield 'day as text' => [
            static fn (FormInterface $f) => FormData::integer($f, 'x'),
            '10',
            'x must be an integer.',
        ];
        yield 'missing amount' => [
            static fn (FormInterface $f) => FormData::decimal($f, 'x'),
            null,
            $numeric,
        ];
        yield 'amount as a float' => [
            static fn (FormInterface $f) => FormData::optionalDecimal($f, 'x'),
            1.5,
            $numeric,
        ];
    }

    /**
     * @param \Closure(FormInterface): mixed $read
     */
    #[DataProvider('wrongValues')]
    public function testAWrongValueNamesTheField(\Closure $read, mixed $value, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $read($this->form(['x' => $value]));
    }

    /**
     * A stand-in for a submitted form that hands back each value as given, as the real field types do.
     *
     * @param array<string, mixed> $data
     */
    private function form(array $data): FormInterface
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('get')->willReturnCallback(function (string $field) use ($data): FormInterface {
            $child = $this->createStub(FormInterface::class);
            $child->method('getData')->willReturn($data[$field] ?? null);

            return $child;
        });

        return $form;
    }
}
