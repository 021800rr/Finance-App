<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Creates users with an already hashed password, so a User never exists with a placeholder credential.
 */
final readonly class UserFactory
{
    public function __construct(private PasswordHasherFactoryInterface $hasherFactory)
    {
    }

    public function create(string $email, string $plainPassword): User
    {
        $hashedPassword = $this->hasherFactory->getPasswordHasher(User::class)->hash($plainPassword);

        return new User($email, $hashedPassword);
    }
}
