<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\ApiResource\Input\AccountInput;
use App\Entity\Account;
use App\OpenApi\MoneySchema;
use App\State\AccountProcessor;
use App\State\AccountProvider;
use App\ValueObject\Money;

/**
 * An account as the API returns it: every field is always there, and the OpenAPI document says so (a generated client
 * gets types without needless nulls). Only GET /api/accounts/{id} adds the balances: the list and the answer to PATCH
 * leave them out, as working them out takes two sums per account. What a client sends is AccountInput (input: on
 * PATCH).
 */
#[ApiResource(
    shortName: 'Account',
    description: 'A bank account. Write access is limited to toggling "hidden" and "isMain", mirroring the web app.',
    operations: [
        new GetCollection(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Lists all accounts, ordered by bank, type, currency and account number.',
            provider: AccountProvider::class,
        ),
        new Get(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Retrieves a single account by id, including its balance ("saldo") and the balance as of '
            .'today ("saldoToday", which leaves out operations dated in the future), '
            .'as on the web account history page.',
            provider: AccountProvider::class,
        ),
        new Patch(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Updates an account. "hidden" mirrors the web app\'s "Hide"/"Show" toggle. '
            .'"isMain" mirrors the ☆ toggle: true makes this the main account and unsets the previous one. '
            .'No other edits are supported.',
            input: AccountInput::class,
            provider: AccountProvider::class,
            processor: AccountProcessor::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final readonly class AccountResource
{
    private const string SALDO_DESCRIPTION =
        'Balance including operations dated in the future. Only in GET /api/accounts/{id}.';
    private const array SALDO_EXAMPLE = ['amount' => '4935.00', 'currency' => 'PLN'];
    private const string SALDO_TODAY_DESCRIPTION =
        'Balance counting only operations dated today or earlier. Only in GET /api/accounts/{id}.';
    private const array SALDO_TODAY_EXAMPLE = ['amount' => '4965.00', 'currency' => 'PLN'];
    private const string FUTURE_DESCRIPTION =
        'True when saldo and saldoToday differ, i.e. future-dated operations exist. Only in GET /api/accounts/{id}.';

    /**
     * @param array{amount: string, currency: string}|null $saldo      left out of the list and of the PATCH answer
     * @param array{amount: string, currency: string}|null $saldoToday left out of the list and of the PATCH answer
     */
    public function __construct(
        #[ApiProperty(description: 'Account id.', required: true, example: 1)]
        public int $id,
        #[ApiProperty(description: 'Account number.', required: true, example: '12 3456 7890 1234 5678 9012 3456')]
        public string $accountNumber,
        #[ApiProperty(
            description: 'Whether the account is hidden from the summary/list views. A hidden account cannot be chosen '
            .'for a new operation or transfer.',
            required: true,
            example: false,
        )]
        public bool $hidden,
        #[ApiProperty(description: 'Whether this is the main account.', required: true, example: true)]
        public bool $isMain,
        #[ApiProperty(description: 'Bank name.', required: true, example: 'mBank')]
        public string $bankName,
        #[ApiProperty(description: 'Currency code.', required: true, example: 'PLN')]
        public string $currencyCode,
        #[ApiProperty(description: 'Account type name.', required: true, example: 'Konto osobiste')]
        public string $typeName,
        #[ApiProperty(description: 'Household name.', required: true, example: 'Dom')]
        public string $householdName,
        #[ApiProperty(
            description: 'Human-readable label combining bank, type, currency and account number.',
            required: true,
            example: 'mBank / Konto osobiste / PLN / 12 3456 7890 1234 5678 9012 3456',
        )]
        public string $displayLabel,
        // The balances are left out when absent, never sent as null: the schema says object, not object or null.
        #[ApiProperty(
            description: self::SALDO_DESCRIPTION,
            example: self::SALDO_EXAMPLE,
            schema: [
                ...MoneySchema::OBJECT,
                'description' => self::SALDO_DESCRIPTION,
                'example' => self::SALDO_EXAMPLE,
            ],
        )]
        public ?array $saldo = null,
        #[ApiProperty(
            description: self::SALDO_TODAY_DESCRIPTION,
            example: self::SALDO_TODAY_EXAMPLE,
            schema: [
                ...MoneySchema::OBJECT,
                'description' => self::SALDO_TODAY_DESCRIPTION,
                'example' => self::SALDO_TODAY_EXAMPLE,
            ],
        )]
        public ?array $saldoToday = null,
        #[ApiProperty(
            description: self::FUTURE_DESCRIPTION,
            example: true,
            schema: ['type' => 'boolean', 'description' => self::FUTURE_DESCRIPTION, 'example' => true],
        )]
        public ?bool $hasFutureOperations = null,
    ) {
    }

    /**
     * The account without its balances, as the list and the answer to PATCH give it.
     */
    public static function fromEntity(Account $account): self
    {
        return new self(
            id: $account->getId(),
            accountNumber: $account->getAccountNumber(),
            hidden: $account->isHidden(),
            isMain: $account->isMain(),
            bankName: $account->getBankName(),
            currencyCode: $account->getCurrencyCode(),
            typeName: $account->getTypeName(),
            householdName: $account->getHouseholdName(),
            displayLabel: $account->getDisplayLabel(),
        );
    }

    /**
     * The same account with its balances, as GET /api/accounts/{id} answers it.
     */
    public function withBalances(Money $saldo, Money $saldoToday, bool $hasFutureOperations): self
    {
        return new self(
            id: $this->id,
            accountNumber: $this->accountNumber,
            hidden: $this->hidden,
            isMain: $this->isMain,
            bankName: $this->bankName,
            currencyCode: $this->currencyCode,
            typeName: $this->typeName,
            householdName: $this->householdName,
            displayLabel: $this->displayLabel,
            saldo: $saldo->toArray(),
            saldoToday: $saldoToday->toArray(),
            hasFutureOperations: $hasFutureOperations,
        );
    }
}
