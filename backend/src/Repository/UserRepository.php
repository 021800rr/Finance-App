<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
final class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    use SaveRemoveTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Looks the user up by the normalized form of the address, so "Ryszard@Example.com " finds "ryszard@example.com".
     * An address that can never be stored (blank, malformed) simply finds nobody.
     */
    public function findOneByEmail(string $email): ?User
    {
        try {
            $normalized = User::normalizeEmail($email);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $this->findOneBy(['email' => $normalized]);
    }

    /**
     * Used by the security user provider for every login attempt.
     */
    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        return $this->findOneByEmail($identifier);
    }
}
