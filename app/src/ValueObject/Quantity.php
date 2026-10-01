<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\ValueObject\Trait\DecimalParsingTrait;

final readonly class Quantity
{
    use DecimalParsingTrait;

    private const int SCALE = 4;

    private function __construct(private int $minorUnits)
    {
        if ($minorUnits < 0) {
            throw new \InvalidArgumentException("Quantity must not be negative, got minor units: $minorUnits.");
        }
    }

    /**
     * Creates Quantity directly from an already-computed minor-unit value (e.g. read from the
     * database), without parsing any string.
     */
    public static function fromMinorUnits(int $minorUnits): self
    {
        return new self($minorUnits);
    }

    /**
     * Creates Quantity from a human-readable decimal string (e.g. typed into a form), parsing and
     * rounding it to the given scale. Rejects negative values.
     */
    public static function fromDecimalString(string $decimal): self
    {
        return new self(self::parseUnsignedDecimalToMinorUnits($decimal, self::SCALE));
    }

    /**
     * Whether the quantity is greater than zero.
     * Used by: setter validation.
     */
    public function isPositive(): bool
    {
        return $this->minorUnits > 0;
    }

    /**
     * Returns the raw value in the smallest unit (ten-thousandths of an ounce) — for storing in
     * the database.
     */
    public function getMinorUnits(): int
    {
        return $this->minorUnits;
    }

    /**
     * Formats the quantity as a plain decimal string (e.g. "1.5000") for display to the user.
     */
    public function toDecimalString(): string
    {
        return self::minorUnitsToDecimalString($this->minorUnits, self::SCALE);
    }

    /**
     * Formats the quantity as a human-readable string (Polish decimal separator, space thousands
     * separator — this is a single-user Polish app, so the formatting isn't locale-parameterized).
     */
    public function format(): string
    {
        return number_format((float) $this->toDecimalString(), self::SCALE, ',', ' ');
    }
}
