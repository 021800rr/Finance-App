<?php

declare(strict_types=1);

namespace App\Validator;

use App\ApiResource\AccountOperationResource;
use App\ApiResource\FixedExpenseResource;
use App\Repository\ContractorRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ContractorGivenValidator extends ConstraintValidator
{
    private const string ID = 'contractorId';
    private const string NAME = 'contractorName';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ContractorRepository $contractorRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ContractorGiven) {
            throw new UnexpectedTypeException($constraint, ContractorGiven::class);
        }

        if (!$value instanceof AccountOperationResource && !$value instanceof FixedExpenseResource) {
            return;
        }

        // Read from the request body: on update the resource also holds the stored contractor, so it cannot tell
        // what the client sent, and on create it cannot tell a sent null from a field left out.
        $body = json_decode((string) $this->requestStack->getCurrentRequest()?->getContent(), true);
        if (!is_array($body)) {
            return;
        }

        $isContractorIdProvided = array_key_exists(self::ID, $body);
        $isContractorNameProvided = array_key_exists(self::NAME, $body);
        $isUpdate = null !== $value->id;

        if ($isUpdate && !$isContractorIdProvided && !$isContractorNameProvided) {
            return;
        }

        $providedContractorId = $body[self::ID] ?? null;
        $providedContractorName = $body[self::NAME] ?? null;
        $trimmedProvidedContractorName = is_string($providedContractorName) ? trim($providedContractorName) : '';

        // {..., contractorName = 'x'}
        if ('' !== $trimmedProvidedContractorName) {
            $this->checkName(
                $constraint,
                $providedContractorId,
                $isContractorIdProvided,
                $trimmedProvidedContractorName,
                $isUpdate
            );
        } // {contractorId = n} || {contractorId = n, contractorName = null|''}
        elseif (null !== $providedContractorId) {
            if ($isContractorNameProvided) {
                $this->report(
                    null === $providedContractorName ? $constraint->nullMessage : $constraint->blankNameMessage,
                    self::NAME
                );
            }
        } // {contractorName = null}
        elseif ($isContractorNameProvided && null === $providedContractorName && !$isContractorIdProvided) {
            $this->report($constraint->nullMessage, self::NAME);
        } // POST {contractorId = null}
        elseif ($isContractorIdProvided && !$isContractorNameProvided && !$isUpdate) {
            $this->report($constraint->nullMessage, self::ID);
        } else {
            $this->report($constraint->requiredMessage, self::NAME);
        }
    }

    private function checkName(
        ContractorGiven $constraint,
        mixed $providedContractorId,
        bool $isContractorIdProvided,
        string $providedContractorName, // 'x'
        bool $isUpdate,
    ): void {
        if (null === $providedContractorId) {
            // POST
            if ($isContractorIdProvided && !$isUpdate) {
                $this->report($constraint->nullMessage, self::ID);
            }
            // PATCH

            return;
        }

        $contractor = $this->contractorRepository->findByIdOrNull($providedContractorId);
        if (null !== $contractor && $contractor->getName() !== $providedContractorName) {
            $this->report($constraint->mismatchMessage, self::NAME);
        }
    }

    private function report(string $message, string $field): void
    {
        $this->context->buildViolation($message)->atPath($field)->addViolation();
    }
}
