<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlankStringGuardTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\AccountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'account')]
#[ORM\Index(name: 'idx_account_bank', columns: ['bank_id'])]
#[ORM\Index(name: 'idx_account_currency', columns: ['currency_code'])]
#[ORM\Index(name: 'idx_account_account_type', columns: ['account_type_id'])]
#[ORM\Index(name: 'idx_account_household', columns: ['household_id'])]
#[ORM\UniqueConstraint(
    name: 'uniq_bank_account_number',
    columns: ['bank_id', 'account_number']
)]
class Account
{
    use TimestampTrait;
    use BlankStringGuardTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(inversedBy: 'accounts')]
    #[ORM\JoinColumn(name: 'bank_id', nullable: false, onDelete: 'RESTRICT')]
    private Bank $bank;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'currency_code', referencedColumnName: 'code', nullable: false, onDelete: 'RESTRICT')]
    private Currency $currency;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'account_type_id', nullable: false, onDelete: 'RESTRICT')]
    private AccountType $type;

    #[ORM\Column(name: 'account_number', length: 50)]
    #[Assert\NotBlank(message: 'account.number_not_blank')]
    #[Assert\Length(min: 1, max: 50, minMessage: 'account.number_min_length', maxMessage: 'account.number_max_length')]
    private string $accountNumber;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $hidden = false;

    #[ORM\Column(name: 'is_main', type: 'boolean', options: ['default' => false])]
    private bool $isMain = false;

    #[ORM\ManyToOne(inversedBy: 'accounts')]
    #[ORM\JoinColumn(name: 'household_id', nullable: false, onDelete: 'RESTRICT')]
    private Household $household;

    /** @var Collection<int, AccountOperation> */
    #[ORM\OneToMany(
        targetEntity: AccountOperation::class,
        mappedBy: 'account',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $operations;

    public function __construct(Bank $bank, Currency $currency, AccountType $type, string $accountNumber, Household $household)
    {
        $this->bank = $bank;
        $this->currency = $currency;
        $this->type = $type;
        $this->household = $household;
        self::assertNotBlank($accountNumber, 'Account number');
        $this->accountNumber = $accountNumber;
        $this->operations = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getBank(): Bank
    {
        return $this->bank;
    }

    public function getBankName(): string
    {
        return $this->bank->getName();
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function getCurrencyCode(): string
    {
        return $this->currency->getCode();
    }

    public function getType(): AccountType
    {
        return $this->type;
    }

    public function getTypeName(): string
    {
        return $this->type->getName();
    }

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    public function getDisplayLabel(): string
    {
        return $this->bank->getName().' / '.$this->type->getName().' / '.$this->currency->getCode().' / '.$this->accountNumber;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    public function isMain(): bool
    {
        return $this->isMain;
    }

    /**
     * Outside of fixtures/tests building a fresh account from scratch, use
     * AccountRepository::setAsMainAccount() instead of calling this with
     * true directly — it's the one place responsible for unsetting the
     * previous main account first, so at most one account stays main (also
     * enforced in the database by the uniq_account_is_main partial unique
     * index).
     */
    public function setMain(bool $isMain): void
    {
        $this->isMain = $isMain;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function getHouseholdName(): string
    {
        return $this->household->getName();
    }

    /** @return Collection<int, AccountOperation> */
    public function getOperations(): Collection
    {
        return $this->operations;
    }
}
