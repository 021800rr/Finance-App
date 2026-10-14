<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlankStringGuardTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\BankRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BankRepository::class)]
#[ORM\Table(name: 'bank')]
#[ORM\UniqueConstraint(name: 'uniq_bank_name', columns: ['name'])]
class Bank
{
    use TimestampTrait;
    use BlankStringGuardTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'bank.name_not_blank')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'bank.name_min_length', maxMessage: 'bank.name_max_length')]
    private string $name;

    /**
     * Never read in PHP, but queried: CurrencyExchangeRateType joins "b.accounts" to offer only banks that have accounts.
     *
     * @var Collection<int, Account>
     */
    #[ORM\OneToMany(targetEntity: Account::class, mappedBy: 'bank')]
    private Collection $accounts;

    public function __construct(string $name)
    {
        self::assertNotBlank($name, 'Bank name');
        $this->accounts = new ArrayCollection();
        $this->name = $name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
