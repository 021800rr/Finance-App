<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlankStringGuardTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\HouseholdRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HouseholdRepository::class)]
#[ORM\Table(name: 'household')]
#[ORM\UniqueConstraint(name: 'uniq_household_name', columns: ['name'])]
class Household
{
    use TimestampTrait;
    use BlankStringGuardTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isOwnFunds;

    public function __construct(string $name, bool $isOwnFunds = true)
    {
        self::assertNotBlank($name, 'Household name');
        $this->name = $name;
        $this->isOwnFunds = $isOwnFunds;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isOwnFunds(): bool
    {
        return $this->isOwnFunds;
    }
}
