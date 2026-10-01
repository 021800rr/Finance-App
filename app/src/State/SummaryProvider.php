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
                'saldo' => self::money($account['saldo']),
            ],
            $summary['accounts']
        );
        $resource->byType = array_map(
            static fn (array $entry) => [
                'raw' => array_map(self::money(...), $entry['raw']),
                'pln' => self::money($entry['pln']),
            ],
            $summary['byType']
        );
        $resource->byHousehold = array_map(self::money(...), $summary['byHousehold']);
        $resource->ownFundsHouseholdName = $summary['ownFundsHouseholdName'];
        $resource->gold = self::money($summary['gold']);
        $resource->retirementBalance = self::money($summary['retirementBalance']);
        $resource->retirementBalanceWithGold = self::money($summary['retirementBalanceWithGold']);
        $resource->accountsMissingRate = $summary['accountsMissingRate'];

        return $resource;
    }

    /**
     * @return array{amount: string, currency: string}
     */
    private static function money(Money $money): array
    {
        return ['amount' => $money->toDecimalString(), 'currency' => $money->getCurrencyCode()];
    }
}
