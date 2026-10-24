<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\FixedExpensePlanResource;
use App\Exception\FixedExpensePlanningException;
use App\Repository\AccountRepository;
use App\Service\FixedExpensePlannerService;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @implements ProcessorInterface<mixed, FixedExpensePlanResource>
 */
final readonly class FixedExpensePlanProcessor implements ProcessorInterface
{
    public function __construct(
        private FixedExpensePlannerService $planner,
        private AccountRepository $accountRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FixedExpensePlanResource
    {
        try {
            $result = $this->planner->planNextMonth();
        } catch (FixedExpensePlanningException $exception) {
            // No main account, or a suspected duplicate: the request conflicts with the current state.
            throw new ConflictHttpException($exception->getMessage(), $exception);
        }

        $mainAccountId = $this->accountRepository->findMain()?->getId();
        if (null === $mainAccountId) {
            throw new \LogicException('The planner succeeded, so a main account must exist.');
        }

        return FixedExpensePlanResource::fromResult($mainAccountId, $result);
    }
}
