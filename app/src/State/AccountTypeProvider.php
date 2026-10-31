<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AccountTypeResource;
use App\Exception\RecordNotFoundException;
use App\Repository\AccountTypeRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<AccountTypeResource>
 */
final readonly class AccountTypeProvider implements ProviderInterface
{
    public function __construct(private AccountTypeRepository $accountTypeRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return AccountTypeResource|list<AccountTypeResource>
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): AccountTypeResource|array {
        if (isset($uriVariables['id'])) {
            $accountType = $this->accountTypeRepository->findByIdOrNull($uriVariables['id']);

            if (!$accountType) {
                $exception = RecordNotFoundException::accountType();

                throw new NotFoundHttpException($exception->getMessage(), $exception);
            }

            return AccountTypeResource::fromEntity($accountType);
        }

        $accountTypes = $this->accountTypeRepository->findBy([], ['name' => 'ASC']);

        return array_map(AccountTypeResource::fromEntity(...), $accountTypes);
    }
}
