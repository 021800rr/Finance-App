<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AccountOperationResource;
use App\Exception\RecordNotFoundException;
use App\Repository\AccountOperationRepository;

/**
 * GET /api/account_operations/{id}/other-side: the other operation of a transfer, found as the web app's 🔗 link
 * finds it (AccountOperationController::goToOtherSide).
 *
 * @implements ProviderInterface<AccountOperationResource>
 */
final readonly class AccountOperationOtherSideProvider implements ProviderInterface
{
    public function __construct(private AccountOperationRepository $accountOperationRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): AccountOperationResource {
        $accountOperation = $this->accountOperationRepository->findByIdOrNull($uriVariables['id'] ?? null)
            ?? throw RecordNotFoundException::accountOperation()->asHttp();
        $otherSide = $this->accountOperationRepository->findOtherSide($accountOperation)
            ?? throw RecordNotFoundException::accountOperationOtherSide()->asHttp();

        return AccountOperationResource::fromEntity($otherSide);
    }
}
