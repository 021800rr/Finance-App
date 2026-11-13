<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * An id given in the request body must point to an existing record. A missing value is left to the other constraints.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class RecordExists extends Constraint
{
    /**
     * @param class-string  $entity
     * @param string[]|null $groups
     */
    public function __construct(
        public string $entity,
        public string $message,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}
