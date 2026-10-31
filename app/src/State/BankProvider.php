<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BankResource;
use App\Exception\RecordNotFoundException;
use App\Repository\BankRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<BankResource>
 */
final readonly class BankProvider implements ProviderInterface
{
    public function __construct(private BankRepository $bankRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return BankResource|list<BankResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BankResource|array
    {
        if (isset($uriVariables['id'])) {
            $bank = $this->bankRepository->findByIdOrNull($uriVariables['id']);

            if (!$bank) {
                $exception = RecordNotFoundException::bank();

                throw new NotFoundHttpException($exception->getMessage(), $exception);
            }

            return BankResource::fromEntity($bank);
        }

        $banks = $this->bankRepository->findBy([], ['name' => 'ASC']);

        return array_map(BankResource::fromEntity(...), $banks);
    }
}
