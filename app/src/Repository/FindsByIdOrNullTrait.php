<?php

declare(strict_types=1);

namespace App\Repository;

use App\Util\IdParser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @template TEntity of object
 *
 * @phpstan-require-extends ServiceEntityRepository<TEntity>
 */
trait FindsByIdOrNullTrait
{
    /**
     * Finds an entity by a raw, untrusted id (URL segment, request body, query string, ...).
     *
     * Ids are PostgreSQL "integer" (int4) columns, while PHP ints are 64-bit: handing find() an id above
     * int4 max makes the database raise "value out of range" (a 500) instead of finding no row. Anything
     * that is not a valid id (non-numeric, zero, negative, above int4 max) is therefore simply "not found".
     *
     * @return TEntity|null
     */
    public function findByIdOrNull(mixed $rawId): ?object
    {
        $id = IdParser::tryParse($rawId);

        return null === $id ? null : $this->find($id);
    }
}
