<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Goes on a resource that chooses its contractor with contractorId and contractorName.
 *
 * On create and update: an id alone must exist (RecordExists), an id with a name must be the same contractor,
 * and a name alone finds the contractor or creates it. A blank name or a null is refused. The only exception:
 * on update, a null id with a name replaces the contractor. An update without these fields keeps the contractor.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ContractorGiven extends Constraint
{
    public string $requiredMessage = 'contractor.id_or_name_required';
    public string $nullMessage = 'contractor.null_not_allowed';
    public string $blankNameMessage = 'contractor.name_not_blank';
    public string $mismatchMessage = 'contractor.id_name_mismatch';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
