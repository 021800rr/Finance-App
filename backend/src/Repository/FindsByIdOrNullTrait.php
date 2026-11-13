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
     * @param mixed $rawId untrusted input of any type; anything that is not a valid id yields null
     *
     * @return TEntity|null
     */
    public function findByIdOrNull(mixed $rawId): ?object
    {
        $id = IdParser::tryParse($rawId);

        return null === $id ? null : $this->find($id);
    }
}
