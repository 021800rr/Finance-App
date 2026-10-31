<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Entity\Account;
use App\State\AccountProcessor;
use App\State\AccountProvider;
use App\Validator\TrueOrOmitted;
use Symfony\Component\Validator\Constraints as Assert;

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
            .'today ("saldoToday", which leaves out operations dated in the future), as on the web account history page.',
            provider: AccountProvider::class,
        ),
        new Patch(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Updates an account. "hidden" mirrors the web app\'s "Hide"/"Show" toggle. '
            .'"isMain" mirrors the ☆ toggle: true makes this the main account and unsets the previous one. '
            .'No other edits are supported.',
            provider: AccountProvider::class,
            processor: AccountProcessor::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final class AccountResource
{
    private const string SALDO_DESCRIPTION =
        'Balance including operations dated in the future. Read-only, returned only by GET.';
    private const string SALDO_TODAY_DESCRIPTION =
        'Balance counting only operations dated today or earlier. Read-only, returned only by GET.';
    private const string IS_MAIN_DESCRIPTION =
        'Send true to make this the main account; the previous main account is unset automatically. '
        .'Leave this field out to keep the current state.';

    #[ApiProperty(description: 'Account id. Read-only, ignored on write.', writable: false, example: 1)]
    public ?int $id = null;

    #[ApiProperty(
        description: 'Account number. Read-only, ignored on write.',
        writable: false,
        example: '12 3456 7890 1234 5678 9012 3456')]
    public ?string $accountNumber = null;

    #[ApiProperty(
        description: 'Whether the account is hidden from the summary/list views. A hidden account cannot be chosen '
        .'for a new operation or transfer; its existing operations can still be updated. Writable.',
        example: false,
    )]
    #[Assert\NotNull(message: 'account.hidden_not_null')]
    public ?bool $hidden = null;

    // Not ?bool: a value of another type has to reach TrueOrOmitted instead of ending as a generic 400.
    #[ApiProperty(
        description: self::IS_MAIN_DESCRIPTION,
        example: true,
        schema: ['type' => 'boolean', 'description' => self::IS_MAIN_DESCRIPTION, 'example' => true],
    )]
    #[TrueOrOmitted]
    public mixed $isMain = null;

    #[ApiProperty(description: 'Bank name. Read-only, ignored on write.', writable: false, example: 'mBank')]
    public ?string $bankName = null;

    #[ApiProperty(description: 'Currency code. Read-only, ignored on write.', writable: false, example: 'PLN')]
    public ?string $currencyCode = null;

    #[ApiProperty(
        description: 'Account type name. Read-only, ignored on write.',
        writable: false,
        example: 'Konto osobiste')]
    public ?string $typeName = null;

    #[ApiProperty(description: 'Household name. Read-only, ignored on write.', writable: false, example: 'Dom')]
    public ?string $householdName = null;

    #[ApiProperty(
        description: 'Human-readable label combining bank, type, currency and account number. Read-only.',
        writable: false,
        example: 'mBank / Konto osobiste / PLN / 12 3456 7890 1234 5678 9012 3456')]
    public ?string $displayLabel = null;

    /** @var array{amount: string, currency: string}|null */
    #[ApiProperty(
        description: self::SALDO_DESCRIPTION,
        writable: false,
        example: ['amount' => '4935.00', 'currency' => 'PLN'],
        schema: [
            'type' => ['object', 'null'],
            'readOnly' => true,
            'description' => self::SALDO_DESCRIPTION,
            'example' => ['amount' => '4935.00', 'currency' => 'PLN'],
            'properties' => [
                'amount' => ['type' => 'string', 'example' => '4935.00'],
                'currency' => ['type' => 'string', 'example' => 'PLN'],
            ],
        ],
    )]
    public ?array $saldo = null;

    /** @var array{amount: string, currency: string}|null */
    #[ApiProperty(
        description: self::SALDO_TODAY_DESCRIPTION,
        writable: false,
        example: ['amount' => '4965.00', 'currency' => 'PLN'],
        schema: [
            'type' => ['object', 'null'],
            'readOnly' => true,
            'description' => self::SALDO_TODAY_DESCRIPTION,
            'example' => ['amount' => '4965.00', 'currency' => 'PLN'],
            'properties' => [
                'amount' => ['type' => 'string', 'example' => '4965.00'],
                'currency' => ['type' => 'string', 'example' => 'PLN'],
            ],
        ],
    )]
    public ?array $saldoToday = null;

    #[ApiProperty(
        description: 'True when saldo and saldoToday differ, i.e. future-dated operations exist. Read-only, returned only by GET.',
        writable: false,
        example: true,
    )]
    public ?bool $hasFutureOperations = null;

    public static function fromEntity(Account $account): self
    {
        $resource = new self();
        $resource->id = $account->getId();
        $resource->accountNumber = $account->getAccountNumber();
        $resource->hidden = $account->isHidden();
        $resource->isMain = $account->isMain();
        $resource->bankName = $account->getBankName();
        $resource->currencyCode = $account->getCurrencyCode();
        $resource->typeName = $account->getTypeName();
        $resource->householdName = $account->getHouseholdName();
        $resource->displayLabel = $account->getDisplayLabel();

        return $resource;
    }
}
