<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contractor;
use App\Repository\ContractorRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Resolves a contractor for a write API operation: either an existing contractor by id,
 * or find-or-create by name (mirrors the web form's autocomplete-or-create behaviour).
 */
final readonly class ContractorResolver
{
    public function __construct(private ContractorRepository $contractorRepository)
    {
    }

    public function resolve(?int $contractorId, ?string $contractorName): Contractor
    {
        if (null !== $contractorId) {
            $contractor = $this->contractorRepository->find($contractorId);
            if (!$contractor) {
                throw new NotFoundHttpException('Contractor not found.');
            }

            return $contractor;
        }

        $trimmedName = trim((string) $contractorName);
        if ('' === $trimmedName) {
            throw new UnprocessableEntityHttpException('Either contractorId or contractorName is required.');
        }

        $contractor = $this->contractorRepository->findOneByName($trimmedName);
        if ($contractor) {
            return $contractor;
        }

        $contractor = new Contractor($trimmedName);
        $this->contractorRepository->save($contractor, flush: true);

        return $contractor;
    }
}
