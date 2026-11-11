<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\ApiResource\Summary\SummaryAccount;
use App\ApiResource\Summary\SummaryTypeTotal;
use App\OpenApi\MoneySchema;
use App\State\SummaryProvider;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * The summary of all accounts as the API returns it: every field is always there, and the OpenAPI document says so
 * (a generated client gets types without needless nulls). Read-only.
 */
#[ApiResource(
    shortName: 'Summary',
    operations: [
        new Get(
            uriTemplate: '/summary',
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Financial summary across all accounts. By default hidden accounts are excluded '
            .'from both the accounts list and all totals, mirroring the web app\'s "Show hidden" toggle. '
            .'Pass ?showHidden=true to include them. An account row in another currency carries "rate": the '
            .'bank\'s latest stored rate to PLN; a PLN account and one whose bank has no rate stored (see '
            .'"missingRate") have none. '
            .'"isMain" marks the main account (the ★ in the web app); at most one account has it. '
            .'"byType", "retirementBalance" and "retirementBalanceWithGold" cover only own-funds accounts; '
            .'"byHousehold" splits the totals of all accounts (and our gold) by household.',
            provider: SummaryProvider::class,
            parameters: [
                'showHidden' => new QueryParameter(
                    schema: ['type' => 'boolean', 'default' => false],
                    description: 'Include hidden accounts in the accounts list and all totals. Defaults to false.',
                ),
            ],
        ),
    ],
    security: "is_granted('ROLE_USER')",
)]
final readonly class SummaryResource
{
    private const string BY_HOUSEHOLD_DESCRIPTION = 'The total in PLN of each household, by household name; our gold '
        .'counts in the own-funds one.';
    private const array BY_HOUSEHOLD_EXAMPLE = [
        'Dom' => ['amount' => '15224.09', 'currency' => 'PLN'],
        'Matka' => ['amount' => '4690.00', 'currency' => 'PLN'],
    ];
    private const string GOLD_DESCRIPTION = 'The value in PLN of our gold, by its latest stored prices.';
    private const array GOLD_EXAMPLE = ['amount' => '8650.00', 'currency' => 'PLN'];
    private const string RETIREMENT_DESCRIPTION = 'The total in PLN of the own-funds retirement accounts.';
    private const array RETIREMENT_EXAMPLE = ['amount' => '1365.00', 'currency' => 'PLN'];
    private const string RETIREMENT_WITH_GOLD_DESCRIPTION = 'retirementBalance plus gold.';
    private const array RETIREMENT_WITH_GOLD_EXAMPLE = ['amount' => '10015.00', 'currency' => 'PLN'];
    private const string MISSING_RATE_DESCRIPTION = 'The bank and number of each account in another currency whose '
        .'bank has no rate to PLN stored, as "<bank> <account number>".';
    private const array MISSING_RATE_EXAMPLE = ['mBank 12 3456 7890 1234 5678 9012 3456'];

    /**
     * @param list<SummaryAccount>                                   $accounts
     * @param array<string, SummaryTypeTotal>                        $byType
     * @param array<string, array{amount: string, currency: string}> $byHousehold
     * @param array{amount: string, currency: string}                $gold
     * @param array{amount: string, currency: string}                $retirementBalance
     * @param array{amount: string, currency: string}                $retirementBalanceWithGold
     * @param list<string>                                           $accountsMissingRate
     */
    public function __construct(
        // API Platform reads no PHPDoc types (there is no PHPDoc parser among the dependencies): nativeType tells it
        // what the items are, so that the OpenAPI document describes them.
        #[ApiProperty(
            description: 'The accounts, in the order of the web app: their sort order, then account number.',
            required: true,
            genId: false,
            nativeType: new CollectionType(
                new GenericType(
                    new BuiltinType(TypeIdentifier::ARRAY),
                    new BuiltinType(TypeIdentifier::INT),
                    new ObjectType(SummaryAccount::class),
                ),
                isList: true,
            ),
        )]
        public array $accounts,
        #[ApiProperty(
            description: 'The totals of the own-funds accounts, by account type name.',
            required: true,
            genId: false,
            nativeType: new CollectionType(
                new GenericType(
                    new BuiltinType(TypeIdentifier::ARRAY),
                    new BuiltinType(TypeIdentifier::STRING),
                    new ObjectType(SummaryTypeTotal::class),
                ),
            ),
        )]
        public array $byType,
        #[ApiProperty(
            description: self::BY_HOUSEHOLD_DESCRIPTION,
            required: true,
            example: self::BY_HOUSEHOLD_EXAMPLE,
            schema: [
                'type' => 'object',
                'description' => self::BY_HOUSEHOLD_DESCRIPTION,
                'example' => self::BY_HOUSEHOLD_EXAMPLE,
                'additionalProperties' => MoneySchema::OBJECT,
            ],
        )]
        public array $byHousehold,
        #[ApiProperty(description: 'Name of the household of our own funds.', required: true, example: 'Dom')]
        public string $ownFundsHouseholdName,
        #[ApiProperty(
            description: self::GOLD_DESCRIPTION,
            required: true,
            example: self::GOLD_EXAMPLE,
            schema: [
                ...MoneySchema::OBJECT,
                'description' => self::GOLD_DESCRIPTION,
                'example' => self::GOLD_EXAMPLE,
            ],
        )]
        public array $gold,
        #[ApiProperty(
            description: self::RETIREMENT_DESCRIPTION,
            required: true,
            example: self::RETIREMENT_EXAMPLE,
            schema: [
                ...MoneySchema::OBJECT,
                'description' => self::RETIREMENT_DESCRIPTION,
                'example' => self::RETIREMENT_EXAMPLE,
            ],
        )]
        public array $retirementBalance,
        #[ApiProperty(
            description: self::RETIREMENT_WITH_GOLD_DESCRIPTION,
            required: true,
            example: self::RETIREMENT_WITH_GOLD_EXAMPLE,
            schema: [
                ...MoneySchema::OBJECT,
                'description' => self::RETIREMENT_WITH_GOLD_DESCRIPTION,
                'example' => self::RETIREMENT_WITH_GOLD_EXAMPLE,
            ],
        )]
        public array $retirementBalanceWithGold,
        #[ApiProperty(
            description: self::MISSING_RATE_DESCRIPTION,
            required: true,
            example: self::MISSING_RATE_EXAMPLE,
            schema: [
                'type' => 'array',
                'description' => self::MISSING_RATE_DESCRIPTION,
                'example' => self::MISSING_RATE_EXAMPLE,
                'items' => ['type' => 'string'],
            ],
        )]
        public array $accountsMissingRate,
    ) {
    }
}
