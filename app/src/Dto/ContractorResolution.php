<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Contractor;

final readonly class ContractorResolution
{
    public function __construct(
        public ?Contractor $contractor,
        public string $contractorName,
        public ?string $contractorId,
        public ?string $contractorErrorMessage,
    ) {
    }
}
