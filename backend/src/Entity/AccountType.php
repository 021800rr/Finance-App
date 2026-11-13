<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlankStringGuardTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\AccountTypeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccountTypeRepository::class)]
#[ORM\Table(name: 'account_type')]
#[ORM\UniqueConstraint(name: 'uniq_account_type_name', columns: ['name'])]
class AccountType
{
    use TimestampTrait;
    use BlankStringGuardTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(length: 50)]
    private string $name;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isRetirement;

    public function __construct(string $name, bool $isRetirement = false)
    {
        $this->setName($name);
        $this->isRetirement = $isRetirement;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        self::assertNotBlank($name, 'Account type name');
        $this->name = $name;
    }

    public function isRetirement(): bool
    {
        return $this->isRetirement;
    }
}
