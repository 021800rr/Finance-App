<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AccountOperationResource;
use App\Entity\AccountOperation;
use App\Repository\AccountOperationRepository;
use App\Repository\AccountRepository;
use App\Service\Paginator;
use App\State\Pagination\OffsetPaginator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<AccountOperationResource|OffsetPaginator<AccountOperationResource>>
 */
final readonly class AccountOperationProvider implements ProviderInterface
{
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
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): AccountOperationResource|OffsetPaginator|null {
        if ($operation instanceof CollectionOperationInterface) {
            return isset($uriVariables['accountId'])
                ? $this->provideAccountHistory($uriVariables['accountId'])
                : $this->provideSearch();
        }

        $accountOperation = $this->accountOperationRepository->findByIdOrNull($uriVariables['id'] ?? null);

        return $accountOperation ? AccountOperationResource::fromEntity($accountOperation) : null;
    }

    /**
     * /accounts/{accountId}/operations: the whole history of one account, newest first, as on the web
     * account history page.
     *
     * @return OffsetPaginator<AccountOperationResource>
     */
    private function provideAccountHistory(mixed $rawAccountId): OffsetPaginator
    {
        $account = $this->accountRepository->findByIdOrNull($rawAccountId);
        if (null === $account) {
            throw new NotFoundHttpException('Account not found.');
        }

        $page = $this->requestedPage($this->requestStack->getCurrentRequest());
        $items = $this->accountOperationRepository->findByAccountOrderedPaged(
            $account,
            Paginator::DEFAULT_PER_PAGE,
            ($page - 1) * Paginator::DEFAULT_PER_PAGE
        );

        return $this->paginate($items, $page, $this->accountOperationRepository->countByAccount($account));
    }

    /**
     * /account_operations?q=...: search over all accounts (the "q" parameter is required, see
     * AccountOperationResource), as on the web search page. A blank query finds nothing.
     *
     * @return OffsetPaginator<AccountOperationResource>
     */
    private function provideSearch(): OffsetPaginator
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = $this->requestedPage($request);
        $rawQuery = $request?->query->get('q');
        $query = is_string($rawQuery) ? trim($rawQuery) : '';

        if ('' === $query) {
            return $this->paginate([], $page, 0);
        }

        $items = $this->accountOperationRepository->searchByQueryPaged(
            $query,
            Paginator::DEFAULT_PER_PAGE,
            ($page - 1) * Paginator::DEFAULT_PER_PAGE
        );

        return $this->paginate($items, $page, $this->accountOperationRepository->countByQuery($query));
    }

    /**
     * @param AccountOperation[] $items
     *
     * @return OffsetPaginator<AccountOperationResource>
     */
    private function paginate(array $items, int $page, int $total): OffsetPaginator
    {
        return new OffsetPaginator(
            array_values(array_map(AccountOperationResource::fromEntity(...), $items)),
            $page,
            Paginator::DEFAULT_PER_PAGE,
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
}
