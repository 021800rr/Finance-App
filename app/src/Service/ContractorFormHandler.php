<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ContractorResolution;
use App\Repository\ContractorRepository;
use App\Util\IdParser;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class ContractorFormHandler
{
    public function __construct(private ContractorRepository $contractorRepository)
    {
    }

    public function resolve(FormInterface $contractorField, Request $request): ContractorResolution
    {
        $rawContractorId = $contractorField->getData();
        $contractorId = is_string($rawContractorId) ? $rawContractorId : null;
        $contractorName = trim((string) $request->request->get('contractor_input', ''));
        $contractor = null;
        $errorMessage = null;

        if (!$contractorId) {
            $errorMessage = 'contractor.required';
            $contractorField->addError(new FormError($errorMessage));
        } elseif (null === IdParser::tryParse($contractorId)) {
            $errorMessage = 'contractor.invalid_id';
            $contractorField->addError(new FormError($errorMessage));
        } else {
            $contractor = $this->contractorRepository->find($contractorId);
            if (!$contractor) {
                $errorMessage = 'contractor.not_found';
                $contractorField->addError(new FormError($errorMessage));
            } else {
                $contractorName = $contractor->getName();
            }
        }

        return new ContractorResolution(
            contractor: $contractor,
            contractorName: $contractorName,
            contractorId: $contractorId,
            contractorErrorMessage: $errorMessage,
        );
    }
}
