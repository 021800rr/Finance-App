<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlankStringGuardTrait;
use App\Entity\Trait\NumericGuardTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\FixedExpenseRepository;
use App\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FixedExpenseRepository::class)]
#[ORM\Table(name: 'fixed_expense')]
#[ORM\Index(name: 'idx_fixed_expense_contractor', columns: ['contractor_id'])]
#[ORM\Index(name: 'idx_fixed_expense_currency', columns: ['currency_code'])]
class FixedExpense
{
    use TimestampTrait;
    use NumericGuardTrait;
    use BlankStringGuardTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'contractor_id', nullable: false, onDelete: 'RESTRICT')]
    private Contractor $contractor;

    #[ORM\Column(type: 'integer')]
    // Simplification: a 1-28 range is valid for every month, including February, at the cost of
    // not being able to set a day of 29-31.
    // You just need to remember to pay a bit earlier: if the bill is normally due on the 31st but
    // you pay on the 28th, nothing bad happens.
    #[Assert\Range(notInRangeMessage: 'fixed_expense.day_out_of_range', min: 1, max: 28)]
    private int $day;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'amount_minor_units', type: 'bigint')]
    #[Assert\Range(notInRangeMessage: 'fixed_expense.amount_out_of_range', min: -99999999, max: -1)]
    private int $amount;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'currency_code', referencedColumnName: 'code', nullable: false, onDelete: 'RESTRICT')]
    private Currency $currency;

    public function __construct(Contractor $contractor, int $day, int $amount, Currency $currency)
    {
        $this->contractor = $contractor;
        $this->currency = $currency;
        $this->setDay($day);
        $this->setAmount($amount);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getContractor(): Contractor
    {
        return $this->contractor;
    }

    public function setContractor(Contractor $contractor): void
    {
        $this->contractor = $contractor;
    }

    public function getDay(): int
    {
        return $this->day;
    }

    public function setDay(int $day): void
    {
        self::assertInRange($day, 1, 28, 'Day');
        $this->day = $day;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        self::assertInRange($amount, -99999999, -1, 'Fixed expense amount');
        $this->amount = $amount;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): void
    {
        $this->currency = $currency;
    }

    public function getAmountAsMoney(): Money
    {
        return Money::fromMinorUnits($this->amount, $this->currency->getCode());
    }
}
