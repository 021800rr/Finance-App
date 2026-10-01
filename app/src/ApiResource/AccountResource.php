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
            description: 'Retrieves a single account by id.',
            provider: AccountProvider::class,
        ),
        new Patch(
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Updates an account. "hidden" mirrors the web app\'s "Ukryj"/"Pokaż" toggle. '
            .'"isMain" mirrors the ☆ toggle: setting it to true makes this the main account and unsets '
            .'the previous one; setting it to false just unsets this account. No other edits are supported.',
            provider: AccountProvider::class,
            processor: AccountProcessor::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final class AccountResource
{
    #[ApiProperty(description: 'Account id. Read-only, ignored on write.', writable: false, example: 1)]
    public ?int $id = null;

    #[ApiProperty(description: 'Account number. Read-only, ignored on write.', writable: false, example: '12 3456 7890 1234 5678 9012 3456')]
    public ?string $accountNumber = null;

    #[ApiProperty(description: 'Whether the account is hidden from the summary/list views. Writable.', example: false)]
    public ?bool $hidden = null;

    #[ApiProperty(
        description: 'Whether this is the main account (used e.g. by "Zaplanuj wydatki"). At most one '
        .'account can be main at a time. Writable.',
        example: false,
    )]
    public ?bool $isMain = null;

    #[ApiProperty(description: 'Bank name. Read-only, ignored on write.', writable: false, example: 'mBank')]
    public ?string $bankName = null;

    #[ApiProperty(description: 'Currency code. Read-only, ignored on write.', writable: false, example: 'PLN')]
    public ?string $currencyCode = null;

    #[ApiProperty(description: 'Account type name. Read-only, ignored on write.', writable: false, example: 'Konto osobiste')]
    public ?string $typeName = null;

    #[ApiProperty(description: 'Household name. Read-only, ignored on write.', writable: false, example: 'Dom')]
    public ?string $householdName = null;

    #[ApiProperty(description: 'Human-readable label combining bank, type, currency and account number. Read-only.', writable: false, example: 'mBank / Konto osobiste / PLN / 12 3456 7890 1234 5678 9012 3456')]
    public ?string $displayLabel = null;

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
