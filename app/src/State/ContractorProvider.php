<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ContractorResource;
use App\Exception\RecordNotFoundException;
use App\Repository\ContractorRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<ContractorResource>
 */
final readonly class ContractorProvider implements ProviderInterface
{
    public function __construct(private ContractorRepository $contractorRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return ContractorResource|list<ContractorResource>
     */
    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ContractorResource|array {
        if (isset($uriVariables['id'])) {
            $contractor = $this->contractorRepository->findByIdOrNull($uriVariables['id']);

            if (!$contractor) {
                $exception = RecordNotFoundException::contractor();

                throw new NotFoundHttpException($exception->getMessage(), $exception);
            }

            return ContractorResource::fromEntity($contractor);
        }

        $filters = $context['filters'] ?? [];
        $name = is_array($filters) && is_string($filters['name'] ?? null) ? $filters['name'] : null;

        if (null !== $name) {
            return array_values(array_map(
                static fn (array $row): ContractorResource => ContractorResource::fromArray($row),
                $this->contractorRepository->searchByName($name, limit: null),
            ));
        }

        $contractors = $this->contractorRepository->findBy([], ['name' => 'ASC']);

        return array_map(ContractorResource::fromEntity(...), $contractors);
    }
}
