<?php

declare(strict_types=1);

namespace App\Util;

final class EntityOrThrow
{
    private function __construct()
    {
    }

    /**
     * @template T of object
     *
     * @param T|null $entity
     *
     * @return T
     */
    public static function require(?object $entity, string $message): object
    {
        if (null === $entity) {
            throw new \RuntimeException($message);
        }

        return $entity;
    }
}
