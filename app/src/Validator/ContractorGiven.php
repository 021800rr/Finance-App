<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Goes on "contractorName" of a resource that uses contractorId or contractorName to choose the contractor.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ContractorGiven extends Constraint
{
    public string $message = 'contractor.id_or_name_required';
}
