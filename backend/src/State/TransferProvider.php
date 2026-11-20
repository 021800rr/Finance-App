<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\TransferResource;
use App\Exception\RecordNotFoundException;
use App\Repository\AccountOperationRepository;
use Symfony\Component\Uid\Uuid;

/**
 * GET /api/transfers/{transactionUuid}: the transfer that POST /api/transfers created, the address its Location
 * names. A transfer is its two operations, the debit and the credit; with one of them deleted it no longer exists.
 *
 * @implements ProviderInterface<TransferResource>
 */
final readonly class TransferProvider implements ProviderInterface
{
    public function __construct(private AccountOperationRepository $accountOperationRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TransferResource
    {
        $uuid = $uriVariables['transactionUuid'] ?? null;
        if (!is_string($uuid) || !Uuid::isValid($uuid)) {
            throw RecordNotFoundException::transfer()->asHttp();
        }

        $out = $in = null;
        $operations = $this->accountOperationRepository->findByTransactionUuid(Uuid::fromString($uuid));
        foreach ($operations as $accountOperation) {
            if ($accountOperation->getAmount() < 0) {
                $out = $accountOperation;
            } else {
                $in = $accountOperation;
            }
        }
        if (2 !== count($operations) || null === $out || null === $in) {
            throw RecordNotFoundException::transfer()->asHttp();
        }

        return TransferResource::fromOperations($out, $in);
    }
}
