<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Form\FormInterface;

final readonly class ContractorFormResult
{
    public function __construct(
        public FormInterface $form,
        public string $contractorName,
        public ?string $contractorId,
        public ?string $contractorErrorMessage,
    ) {
    }
}
