<?php

declare(strict_types=1);

namespace App\Form;

use App\Util\OptionalText;
use Symfony\Component\Form\FormInterface;

/**
 * Typed reads of a submitted, already valid form. The form's own constraints refuse missing or wrong values first,
 * so the exceptions here only stop a programming error, e.g. a factory called with a form that was never validated.
 */
final class FormData
{
    private function __construct()
    {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public static function entity(FormInterface $form, string $field, string $class): object
    {
        $value = $form->get($field)->getData();
        if (!$value instanceof $class) {
            throw new \InvalidArgumentException(sprintf('%s is required.', $field));
        }

        /** @var T $entity */
        $entity = $value;

        return $entity;
    }

    public static function date(FormInterface $form, string $field): \DateTimeImmutable
    {
        return self::entity($form, $field, \DateTimeImmutable::class);
    }

    public static function integer(FormInterface $form, string $field): int
    {
        $value = $form->get($field)->getData();
        if (!is_int($value)) {
            throw new \InvalidArgumentException(sprintf('%s must be an integer.', $field));
        }

        return $value;
    }

    /**
     * A MoneyType or NumberType field with "input: string": the number as text, parsed later by Money or Quantity.
     */
    public static function decimal(FormInterface $form, string $field): string
    {
        return self::optionalDecimal($form, $field)
            ?? throw new \InvalidArgumentException(sprintf('%s must be a numeric value.', $field));
    }

    public static function optionalDecimal(FormInterface $form, string $field): ?string
    {
        $value = $form->get($field)->getData();
        if (null !== $value && !is_string($value)) {
            throw new \InvalidArgumentException(sprintf('%s must be a numeric value.', $field));
        }

        return $value;
    }

    /**
     * Optional free text, trimmed, with blank stored as null.
     */
    public static function optionalText(FormInterface $form, string $field): ?string
    {
        $value = $form->get($field)->getData();

        return OptionalText::normalize(is_string($value) ? $value : null);
    }
}
