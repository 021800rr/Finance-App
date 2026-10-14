<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlankStringGuardTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\AccountTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

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
    #[Assert\NotBlank(message: 'account_type.name_not_blank')]
    #[Assert\Length(
        min: 2,
        max: 50,
        minMessage: 'account_type.name_min_length',
        maxMessage: 'account_type.name_max_length'
    )]
    private string $name;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isRetirement = false;

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
