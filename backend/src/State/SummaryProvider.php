<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\ApiResource\Summary\SummaryAccount;
use App\ApiResource\Summary\SummaryTypeTotal;
use App\ApiResource\SummaryResource;
use App\Repository\AccountRepository;
use App\Service\SummaryService;
use App\ValueObject\Money;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProviderInterface<SummaryResource>
 */
final readonly class SummaryProvider implements ProviderInterface
{
    public function __construct(
        private SummaryService $summaryService,
        private AccountRepository $accountRepository,
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
        $summary = $this->summaryService->getSummary($this->showHidden());
        $ratesByBank = $summary['ratesByBank'];
        $mainAccountId = $this->accountRepository->findMain()?->getId();

        return new SummaryResource(
            accounts: array_map(
                static fn (array $account): SummaryAccount => SummaryAccount::fromRow(
                    $account,
                    isMain: $account['id'] === $mainAccountId,
                    // The bank's latest rate to PLN (the web app's "Rate" column); none for PLN accounts and when no
                    // rate is stored.
                    rate: $ratesByBank[$account['bank']][$account['currency']] ?? null,
                ),
                $summary['accounts'],
            ),
            byType: array_map(SummaryTypeTotal::fromTotal(...), $summary['byType']),
            byHousehold: array_map(static fn (Money $money): array => $money->toArray(), $summary['byHousehold']),
            ownFundsHouseholdName: $summary['ownFundsHouseholdName'],
            gold: $summary['gold']->toArray(),
            retirementBalance: $summary['retirementBalance']->toArray(),
            retirementBalanceWithGold: $summary['retirementBalanceWithGold']->toArray(),
            accountsMissingRate: $summary['accountsMissingRate'],
        );
    }

    /**
     * ?showHidden= as a boolean. Anything that is not one is a wrong-type violation like any other
     * ("validation.invalid_type" with the JSON type), refused with 400 as a query parameter.
     */
    private function showHidden(): bool
    {
        try {
            return $this->requestStack->getCurrentRequest()?->query->getBoolean('showHidden') ?? false;
        } catch (BadRequestException $exception) {
            $violation = new ConstraintViolation(
                message: 'This value should be of type boolean.',
                messageTemplate: 'This value should be of type {{ type }}.',
                parameters: ['{{ type }}' => 'boolean'],
                root: null,
                propertyPath: 'showHidden',
                invalidValue: null,
                code: Type::INVALID_TYPE_ERROR,
                cause: $exception,
            );

            throw new ValidationException(new ConstraintViolationList([$violation]));
        }
    }
}
