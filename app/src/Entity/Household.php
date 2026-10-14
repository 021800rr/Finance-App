<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlankStringGuardTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\HouseholdRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

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
    #[Assert\NotBlank(message: 'household.name_not_blank')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'household.name_min_length', maxMessage: 'household.name_max_length')]
    private string $name;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isOwnFunds = true;

    /** @var Collection<int, Account> */
    #[ORM\OneToMany(targetEntity: Account::class, mappedBy: 'household')]
    private Collection $accounts;

    public function __construct(string $name, bool $isOwnFunds = true)
    {
        $this->accounts = new ArrayCollection();
        $this->setName($name);
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

    public function setName(string $name): void
    {
        self::assertNotBlank($name, 'Household name');
        $this->name = $name;
    }

    public function isOwnFunds(): bool
    {
        return $this->isOwnFunds;
    }
}
