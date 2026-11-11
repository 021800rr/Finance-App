<?php

declare(strict_types=1);

namespace App\ApiResource\Summary;

use ApiPlatform\Metadata\ApiProperty;
use App\OpenApi\MoneySchema;
use App\Service\SummaryService;
use App\ValueObject\Money;

/**
 * One account of the summary, its fields named as GET /api/accounts names them, with its balance and the rate that
 * converts it to PLN. Every field is always there, the rate only when there is one.
 *
 * @phpstan-import-type PublicAccountRow from SummaryService
 */
final readonly class SummaryAccount
{
    private const string SALDO_DESCRIPTION = 'Balance, in the currency of the account.';
    private const array SALDO_EXAMPLE = ['amount' => '1629.00', 'currency' => 'CHF'];
    private const string RATE_DESCRIPTION = 'The bank\'s latest stored rate to PLN, for an account in another '
        .'currency; left out for a PLN account and when the bank has no rate stored (see missingRate).';
    private const array RATE_EXAMPLE = ['amount' => '4.5590', 'currency' => 'PLN'];

    /**
     * @param array{amount: string, currency: string}      $saldo
     * @param array{amount: string, currency: string}|null $rate  left out of the answer when there is none
     */
    public function __construct(
        #[ApiProperty(description: 'Account id.', required: true, example: 3)]
        public int $id,
        #[ApiProperty(description: 'Bank name.', required: true, example: 'mBank')]
        public string $bankName,
        #[ApiProperty(description: 'Account type name.', required: true, example: 'Wakacje')]
        public string $typeName,
        #[ApiProperty(description: 'Currency code.', required: true, example: 'CHF')]
        public string $currencyCode,
        #[ApiProperty(description: 'Account number.', required: true, example: '12 3456 7890 1234 5678 9012 3456')]
        public string $accountNumber,
        #[ApiProperty(
            description: 'Whether the account is hidden; a hidden one is listed only with ?showHidden=true.',
            required: true,
            example: false,
        )]
        public bool $hidden,
        #[ApiProperty(description: 'Household name.', required: true, example: 'Dom')]
        public string $householdName,
        #[ApiProperty(
            description: 'Whether the account holds our own funds: only these count in byType and the retirement '
            .'balances.',
            required: true,
            example: true,
        )]
        public bool $isOwnFunds,
        #[ApiProperty(description: 'Whether the account type is a retirement one.', required: true, example: false)]
        public bool $isRetirement,
        #[ApiProperty(
            description: self::SALDO_DESCRIPTION,
            required: true,
            example: self::SALDO_EXAMPLE,
            schema: [
                ...MoneySchema::OBJECT,
                'description' => self::SALDO_DESCRIPTION,
                'example' => self::SALDO_EXAMPLE,
            ],
        )]
        public array $saldo,
        #[ApiProperty(
            description: 'True for an account in another currency whose bank has no rate to PLN stored: its balance '
            .'is left out of the totals in PLN.',
            required: true,
            example: false,
        )]
        public bool $missingRate,
        #[ApiProperty(
            description: 'Whether this is the main account (the ★ in the web app); at most one account is.',
            required: true,
            example: false,
        )]
        public bool $isMain,
        // Left out when absent, never sent as null: the schema says object, not object or null.
        #[ApiProperty(
            description: self::RATE_DESCRIPTION,
            example: self::RATE_EXAMPLE,
            schema: [
                ...MoneySchema::OBJECT,
                'description' => self::RATE_DESCRIPTION,
                'example' => self::RATE_EXAMPLE,
            ],
        )]
        public ?array $rate,
    ) {
    }

    /**
     * @param PublicAccountRow $row
     */
    public static function fromRow(array $row, bool $isMain, ?Money $rate): self
    {
        return new self(
            id: $row['id'],
            bankName: $row['bank'],
            typeName: $row['type'],
            currencyCode: $row['currency'],
            accountNumber: $row['account_number'],
            hidden: $row['hidden'],
            householdName: $row['household'],
            isOwnFunds: $row['is_own_funds'],
            isRetirement: $row['is_retirement'],
            saldo: $row['saldo']->toArray(),
            missingRate: $row['missing_rate'],
            isMain: $isMain,
            rate: $rate?->toArray(),
        );
    }
}
