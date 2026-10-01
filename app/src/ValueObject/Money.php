<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\Constant\Currencies;
use App\ValueObject\Trait\DecimalParsingTrait;

final readonly class Money
{
    use DecimalParsingTrait;

    private function __construct(
        private int $minorUnits,
        private string $currencyCode,
        private int $scale,
    ) {
    }

    /**
     * Creates Money directly from an already-computed minor-unit value (e.g. read from the
     * database), without parsing any string.
     */
    public static function fromMinorUnits(
        int $minorUnits,
        string $currencyCode,
        int $scale = Currencies::MINOR_UNIT_SCALE,
    ): self {
        return new self($minorUnits, strtoupper($currencyCode), $scale);
    }

    /**
     * Creates Money from a human-readable decimal string (e.g. typed into a form), parsing and
     * rounding it to the given scale.
     */
    public static function fromDecimalString(
        string $decimal,
        string $currencyCode,
        int $scale = Currencies::MINOR_UNIT_SCALE,
    ): self {
        return new self(self::parseSignedDecimalToMinorUnits($decimal, $scale), strtoupper($currencyCode), $scale);
    }

    /**
     * Creates a zero amount in the given currency — a convenient starting point for summing in a
     * loop, without needing to special-case the first element.
     */
    public static function zero(string $currencyCode, int $scale = Currencies::MINOR_UNIT_SCALE): self
    {
        return new self(0, strtoupper($currencyCode), $scale);
    }

    /**
     * Adds two amounts of the same currency and scale, returning a new Money object.
     * Throws if the currencies or scales differ.
     * Used by: SummaryService::getSummary() and similar.
     */
    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        $this->assertSameScale($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currencyCode, $this->scale);
    }

    /**
     * Returns the amount with its sign flipped (a positive amount becomes negative and vice versa).
     * Used by: TransactionService — generating the outgoing leg of a transfer.
     */
    public function negate(): self
    {
        return new self(-$this->minorUnits, $this->currencyCode, $this->scale);
    }

    /**
     * Converts the amount to PLN using the given exchange rate, returning a new Money object in PLN.
     * The app only settles in PLN, so the target currency is hardcoded.
     */
    public function convertToPln(string $exchangeRateDecimal, int $rateScale = Currencies::EXCHANGE_RATE_SCALE): self
    {
        if (Currencies::SETTLEMENT_CURRENCY_CODE === $this->currencyCode) {
            throw new \LogicException("convertToPln() called on an amount already in {$this->currencyCode}.");
        }

        if (Currencies::MINOR_UNIT_SCALE !== $this->scale) {
            throw new \LogicException('convertToPln() assumes a source scale of '.Currencies::MINOR_UNIT_SCALE.", got scale $this->scale.");
        }

        $rateMinorUnits = self::parseSignedDecimalToMinorUnits($exchangeRateDecimal, $rateScale);
        $converted = self::divideRoundingHalfUp($this->minorUnits * $rateMinorUnits, 10 ** $rateScale);

        return new self($converted, Currencies::SETTLEMENT_CURRENCY_CODE, Currencies::MINOR_UNIT_SCALE);
    }

    /**
     * Integer division with round-half-away-from-zero, unlike intdiv(), which always truncates
     * towards zero. Keeps rounding consistent with DecimalParsingTrait::roundToMinorUnits().
     */
    private static function divideRoundingHalfUp(int $numerator, int $denominator): int
    {
        $sign = ($numerator < 0) === ($denominator < 0) ? 1 : -1;
        $numerator = abs($numerator);
        $denominator = abs($denominator);

        $quotient = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        if (2 * $remainder >= $denominator) {
            ++$quotient;
        }

        return $sign * $quotient;
    }

    /**
     * Whether the amount is positive.
     * Used by: validation in GoldPriceFactory — a gold price must be positive.
     */
    public function isPositive(): bool
    {
        return $this->minorUnits > 0;
    }

    /**
     * Whether the amount is negative (e.g. an expense).
     * Used by: Twig styling (green/red amount color).
     */
    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    /**
     * Whether the amount is exactly zero.
     * Used by: setter validation.
     */
    public function isZero(): bool
    {
        return 0 === $this->minorUnits;
    }

    /**
     * Compares two amounts by value, currency and scale — all three must match for the objects to
     * be considered equal. (Tests.).
     */
    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits
            && $this->currencyCode === $other->currencyCode
            && $this->scale === $other->scale;
    }

    /**
     * Returns the raw value in the smallest unit (e.g. grosze) — for storing in the database.
     */
    public function getMinorUnits(): int
    {
        return $this->minorUnits;
    }

    /**
     * Returns the currency code (e.g. "PLN", "CHF").
     * Used by: logging/debugging.
     */
    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    /**
     * Returns the number of decimal places used by this amount. (Tests.).
     */
    public function getScale(): int
    {
        return $this->scale;
    }

    /**
     * Formats the amount as a human-readable string (Polish decimal separator, space thousands
     * separator — this is a single-user Polish app, so the formatting isn't locale-parameterized).
     * Used by: MoneyExtension — the only Twig filter for displaying amounts.
     */
    public function format(bool $withCurrency = false): string
    {
        $formatted = number_format((float) $this->toDecimalString(), $this->scale, ',', ' ');

        return $withCurrency ? "{$formatted} {$this->currencyCode}" : $formatted;
    }

    /**
     * Formats the amount as a plain decimal string (e.g. "123.45") for display to the user.
     */
    public function toDecimalString(): string
    {
        return self::minorUnitsToDecimalString($this->minorUnits, $this->scale);
    }

    /**
     * Guards against an arithmetic operation on two amounts in different currencies
     * (e.g. trying to add PLN to CHF).
     */
    private function assertSameCurrency(self $other): void
    {
        if ($this->currencyCode !== $other->currencyCode) {
            throw new \InvalidArgumentException("Cannot operate on Money with different currencies: $this->currencyCode vs $other->currencyCode.");
        }
    }

    /**
     * Guards against an arithmetic operation on two amounts with different precision
     * (different number of decimal places).
     */
    private function assertSameScale(self $other): void
    {
        if ($this->scale !== $other->scale) {
            throw new \InvalidArgumentException("Cannot operate on Money with different scales: $this->scale vs $other->scale.");
        }
    }
}
