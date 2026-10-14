<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\SummaryResource;
use App\Service\SummaryService;
use App\ValueObject\Money;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<SummaryResource>
 */
final readonly class SummaryProvider implements ProviderInterface
{
    public function __construct(
        private SummaryService $summaryService,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @throws Exception
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SummaryResource
    {
        $showHidden = $this->requestStack->getCurrentRequest()?->query->getBoolean('showHidden') ?? false;

        $summary = $this->summaryService->getSummary($showHidden);

        $resource = new SummaryResource();
        $resource->accounts = array_map(
            static fn (array $account) => [
                ...$account,
                'saldo' => $account['saldo']->toArray(),
            ],
            $summary['accounts']
        );
        $resource->byType = array_map(
            static fn (array $entry) => [
                'raw' => array_map(
                    static fn (Money $money): array => $money->toArray(),
                    $entry['raw']
                ),
                'pln' => $entry['pln']->toArray(),
            ],
            $summary['byType']
        );
        $resource->byHousehold = array_map(
            static fn (Money $money): array => $money->toArray(),
            $summary['byHousehold']
        );
        $resource->ownFundsHouseholdName = $summary['ownFundsHouseholdName'];
        $resource->gold = $summary['gold']->toArray();
        $resource->retirementBalance = $summary['retirementBalance']->toArray();
        $resource->retirementBalanceWithGold = $summary['retirementBalanceWithGold']->toArray();
        $resource->accountsMissingRate = $summary['accountsMissingRate'];

        return $resource;
    }
}
