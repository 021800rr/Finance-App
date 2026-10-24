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

    /**
     * Resolves the contractor for a PATCH. The API resource handed to the processor is the stored record
     * with the request's fields merged over it, so it always carries the current contractor's id and name.
     * A client that sends only a new "contractorName" therefore still has the old "contractorId" here, and
     * going through resolve() would silently keep the old contractor. Instead, whichever field differs from
     * the current contractor is what the client changed: a different id wins, otherwise a different name.
     */
    public function resolveForUpdate(?int $contractorId, ?string $contractorName, Contractor $current): Contractor
    {
        if ($contractorId !== $current->getId()) {
            return $this->resolve($contractorId, $contractorName);
        }

        if (null === $contractorName || trim($contractorName) === $current->getName()) {
            return $current;
        }

        return $this->resolve(null, $contractorName);
    }

    public function resolve(?int $contractorId, ?string $contractorName): Contractor
    {
        if (null !== $contractorId) {
            $contractor = $this->contractorRepository->findByIdOrNull($contractorId);
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
