<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AccountOperationResource;
use App\Repository\AccountOperationRepository;
use App\Repository\AccountRepository;
use App\State\Pagination\OffsetPaginator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<AccountOperationResource|OffsetPaginator<AccountOperationResource>>
 */
final readonly class AccountOperationProvider implements ProviderInterface
{
    private const int ITEMS_PER_PAGE = 30;

    // Pages beyond this are simply empty; the cap keeps the offset calculation far away from integer overflow.
    private const int MAX_PAGE = 1_000_000;

    public function __construct(
        private AccountOperationRepository $accountOperationRepository,
        private AccountRepository $accountRepository,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return AccountOperationResource|OffsetPaginator<AccountOperationResource>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AccountOperationResource|OffsetPaginator|null
    {
        if (isset($uriVariables['id'])) {
            $accountOperation = $this->accountOperationRepository->findByIdOrNull($uriVariables['id']);

            return $accountOperation ? AccountOperationResource::fromEntity($accountOperation) : null;
        }

        return $this->provideCollection();
    }

    /**
     * Supported query parameters: accountId (only that account), q (text/amount search, see
     * AccountOperationRepository) and page. accountId and q can be combined.
     *
     * @return OffsetPaginator<AccountOperationResource>
     */
    private function provideCollection(): OffsetPaginator
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = $this->requestedPage($request);
        $offset = ($page - 1) * self::ITEMS_PER_PAGE;
        $rawAccountId = $request?->query->get('accountId');
        $rawQuery = $request?->query->get('q');
        $query = is_string($rawQuery) ? trim($rawQuery) : null;

        $account = null;
        if (null !== $rawAccountId) {
            $account = $this->accountRepository->findByIdOrNull($rawAccountId);
            if (!$account) {
                return $this->emptyPage($page);
            }
        }

        if (null !== $query) {
            if ('' === $query) {
                return $this->emptyPage($page);
            }

            $items = $this->accountOperationRepository->searchByQueryPaged($query, self::ITEMS_PER_PAGE, $offset, $account);
            $total = $this->accountOperationRepository->countByQuery($query, $account);
        } elseif (null !== $account) {
            $items = $this->accountOperationRepository->findByAccountOrderedPaged($account, self::ITEMS_PER_PAGE, $offset);
            $total = $this->accountOperationRepository->countByAccount($account);
        } else {
            $items = $this->accountOperationRepository->findAllOrderedPaged(self::ITEMS_PER_PAGE, $offset);
            $total = $this->accountOperationRepository->countAll();
        }

        return new OffsetPaginator(
            array_values(array_map(AccountOperationResource::fromEntity(...), $items)),
            $page,
            self::ITEMS_PER_PAGE,
            $total,
        );
    }

    /**
     * The page number from ?page=, forced into the range 1..MAX_PAGE ("abc", 0 and -5 become 1).
     */
    private function requestedPage(?Request $request): int
    {
        $page = (int) $request?->query->get('page', 1);

        if ($page < 1) {
            return 1;
        }

        if ($page > self::MAX_PAGE) {
            return self::MAX_PAGE;
        }

        return $page;
    }

    /**
     * @return OffsetPaginator<AccountOperationResource>
     */
    private function emptyPage(int $page): OffsetPaginator
    {
        return new OffsetPaginator([], $page, self::ITEMS_PER_PAGE, 0);
    }
}
