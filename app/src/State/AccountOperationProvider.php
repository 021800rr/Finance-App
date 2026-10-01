<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AccountOperationResource;
use App\Repository\AccountOperationRepository;
use App\Repository\AccountRepository;
use App\State\Pagination\OffsetPaginator;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<AccountOperationResource|OffsetPaginator<AccountOperationResource>>
 */
final readonly class AccountOperationProvider implements ProviderInterface
{
    private const int ITEMS_PER_PAGE = 30;

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
            $accountOperation = $this->accountOperationRepository->find($uriVariables['id']);

            return $accountOperation ? AccountOperationResource::fromEntity($accountOperation) : null;
        }

        return $this->provideCollection();
    }

    /**
     * @return OffsetPaginator<AccountOperationResource>
     */
    private function provideCollection(): OffsetPaginator
    {
        $request = $this->requestStack->getCurrentRequest();
        $page = max(1, (int) $request?->query->get('page', 1));
        $rawAccountId = $request?->query->get('accountId');
        $offset = ($page - 1) * self::ITEMS_PER_PAGE;

        if (null !== $rawAccountId) {
            $account = $this->accountRepository->findByIdOrNull($rawAccountId);
            if (!$account) {
                return new OffsetPaginator([], $page, self::ITEMS_PER_PAGE, 0);
            }

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
}
