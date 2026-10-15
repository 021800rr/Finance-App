<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlankStringGuardTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_app_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimestampTrait;
    use BlankStringGuardTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 180)]
    private string $email;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private string $password;

    /**
     * @param string $hashedPassword an already hashed password, never plaintext (see UserFactory)
     */
    public function __construct(string $email, string $hashedPassword)
    {
        $this->email = self::normalizeEmail($email);
        $this->password = $hashedPassword;
    }

    /**
     * The single place that defines what a stored login looks like: trimmed, lower case, a valid address.
     */
    public static function normalizeEmail(string $email): string
    {
        $normalized = mb_strtolower(trim($email));
        self::assertNotBlank($normalized, 'Email');
        if (false === filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(sprintf('Email "%s" is not a valid address.', $normalized));
        }

        return $normalized;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Symfony Security uses this as the unique identifier (session, "remember me", etc.).
     */
    public function getUserIdentifier(): string
    {
        if ('' === $this->email) {
            throw new \LogicException('User email must not be empty.');
        }

        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * No plaintext credentials are ever held on this entity outside the hash, so there is nothing to erase.
     */
    public function eraseCredentials(): void
    {
    }
}
