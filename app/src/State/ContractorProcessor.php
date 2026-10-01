<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\ContractorResource;
use App\Entity\Contractor;
use App\Repository\ContractorRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<mixed, ContractorResource>
 */
final readonly class ContractorProcessor implements ProcessorInterface
{
    public function __construct(private ContractorRepository $contractorRepository)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ContractorResource
    {
        if (!$data instanceof ContractorResource) {
            throw new \InvalidArgumentException('Expected instance of ContractorResource.');
        }

        $trimmedName = trim((string) $data->name);
        if ('' === $trimmedName) {
            throw new UnprocessableEntityHttpException('Contractor name is required.');
        }

        if (null !== $this->contractorRepository->findOneByName($trimmedName)) {
            throw new ConflictHttpException('A contractor with this name already exists.');
        }

        try {
            $contractor = new Contractor($trimmedName);
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        $this->contractorRepository->save($contractor, flush: true);

        return ContractorResource::fromEntity($contractor);
    }
}
