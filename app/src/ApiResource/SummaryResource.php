<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\State\SummaryProvider;

#[ApiResource(
    shortName: 'Summary',
    operations: [
        new Get(
            uriTemplate: '/summary',
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
            description: 'Financial summary across all accounts. By default hidden accounts are excluded '
            .'from both the accounts list and all totals, mirroring the web app\'s "Show hidden" toggle. '
            .'Pass ?showHidden=true to include them.',
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
final class SummaryResource
{
    /** @var list<array<string, mixed>> */
    public array $accounts = [];

    /** @var array<string, array{raw: array<string, array{amount: string, currency: string}>, pln: array{amount: string, currency: string}}> */
    public array $byType = [];

    /** @var array<string, array{amount: string, currency: string}> */
    public array $byHousehold = [];

    public string $ownFundsHouseholdName = '';

    /** @var array{amount: string, currency: string}|null */
    public ?array $gold = null;

    /** @var array{amount: string, currency: string}|null */
    public ?array $retirementBalance = null;

    /** @var array{amount: string, currency: string}|null */
    public ?array $retirementBalanceWithGold = null;

    /** @var list<string> */
    public array $accountsMissingRate = [];
}
