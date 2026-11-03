<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AccountResource;
use App\Entity\Account;
use App\Exception\AccountException;
use App\Repository\AccountRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * The resource validators refuse a missing or unknown record and an invalid value before this runs, so the checks in
 * the private methods below never fire through the API. They stay as a guard for direct calls, which the tests use.
 *
 * @implements ProcessorInterface<mixed, AccountResource>
 */
final readonly class AccountProcessor implements ProcessorInterface
{
    public function __construct(private AccountRepository $accountRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AccountResource
    {
        if (!$data instanceof AccountResource) {
            throw new \InvalidArgumentException('Expected instance of AccountResource.');
        }

        $account = $this->loadExisting($uriVariables);

        if (null === $data->hidden) {
            throw new UnprocessableEntityHttpException('The "hidden" field is required.');
        }

        $account->setHidden($data->hidden);

        if (true === $data->isMain && !$account->isMain()) {
            // Flushes internally (and, as a side effect, persists the "hidden" change set
            // above too — see AccountRepository::setAsMainAccount() for why this can't be a
            // single flush together with unsetting the previous main account).
            try {
                $this->accountRepository->setAsMainAccount($account);
            } catch (UniqueConstraintViolationException) {
                $exception = AccountException::mainChangedConcurrently();

                throw new ConflictHttpException($exception->getMessage(), $exception);
            }
        } else {
            $this->accountRepository->save($account, flush: true);
        }

        return AccountResource::fromEntity($account);
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function loadExisting(array $uriVariables): Account
    {
        $account = $this->accountRepository->findByIdOrNull($uriVariables['id'] ?? null);

        if (!$account) {
            throw new NotFoundHttpException('Account not found.');
        }

        return $account;
    }
}
