<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\NumericGuardTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\AccountOperationRepository;
use App\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AccountOperationRepository::class)]
#[ORM\Table(name: 'account_operation')]
#[ORM\Index(name: 'idx_account_operation_account', columns: ['account_id'])]
#[ORM\Index(name: 'idx_account_operation_contractor', columns: ['contractor_id'])]
#[ORM\Index(name: 'idx_account_operation_currency', columns: ['currency_code'])]
#[ORM\Index(name: 'idx_account_operation_transaction_uuid', columns: ['transaction_uuid'])]
class AccountOperation
{
    use TimestampTrait;
    use NumericGuardTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'account_id', nullable: false, onDelete: 'RESTRICT')]
    private Account $account;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'contractor_id', nullable: false, onDelete: 'RESTRICT')]
    private Contractor $contractor;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotBlank(message: 'common.date_not_blank')]
    private \DateTimeImmutable $operationDate;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $confirmed = false;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $transactionUuid = null;

    #[ORM\Column(name: 'amount_minor_units', type: 'bigint')]
    #[Assert\NotEqualTo(value: 0, message: 'common.amount_cannot_be_zero')]
    #[Assert\Range(notInRangeMessage: 'common.amount_out_of_range', min: -99999999, max: 99999999)]
    private int $amount;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'currency_code', referencedColumnName: 'code', nullable: false, onDelete: 'RESTRICT')]
    private Currency $currency;

    public function __construct(
        Account $account,
        Contractor $contractor,
        \DateTimeImmutable $operationDate,
        int $amount,
        Currency $currency,
        ?string $description = null,
        bool $confirmed = false,
        ?Uuid $transactionUuid = null,
    ) {
        self::assertSameCurrency($currency, $account);
        $this->account = $account;
        $this->contractor = $contractor;
        $this->operationDate = $operationDate;
        $this->setAmount($amount);
        $this->currency = $currency;
        $this->description = $description;
        $this->confirmed = $confirmed;
        $this->transactionUuid = $transactionUuid;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function getContractor(): Contractor
    {
        return $this->contractor;
    }

    public function setContractor(Contractor $contractor): void
    {
        $this->contractor = $contractor;
    }

    public function getOperationDate(): \DateTimeImmutable
    {
        return $this->operationDate;
    }

    public function setOperationDate(\DateTimeImmutable $operationDate): void
    {
        $this->operationDate = $operationDate;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    public function setConfirmed(bool $confirmed): void
    {
        $this->confirmed = $confirmed;
    }

    public function getTransactionUuid(): ?Uuid
    {
        return $this->transactionUuid;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        self::assertNotZero($amount, 'Amount');
        self::assertInRange($amount, -99999999, 99999999, 'Amount');
        $this->amount = $amount;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function getAmountAsMoney(): Money
    {
        return Money::fromMinorUnits($this->amount, $this->currency->getCode());
    }

    /**
     * An operation is always in the currency of its account; anything else would make every balance wrong.
     */
    private static function assertSameCurrency(Currency $currency, Account $account): void
    {
        if ($currency->getCode() !== $account->getCurrencyCode()) {
            throw new \InvalidArgumentException("Operation currency ({$currency->getCode()}) must match the account currency ({$account->getCurrencyCode()}).");
        }
    }
}
