<?php

declare(strict_types=1);

namespace App\Entity;

use App\Constant\Currencies;
use App\Entity\Trait\NumericGuardTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\CurrencyExchangeRateRepository;
use App\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CurrencyExchangeRateRepository::class)]
#[ORM\Table(name: 'currency_exchange_rate')]
#[ORM\Index(name: 'idx_currency_exchange_rate_bank', columns: ['bank_id'])]
#[ORM\Index(name: 'idx_currency_exchange_rate_currency', columns: ['currency_code'])]
class CurrencyExchangeRate
{
    use TimestampTrait;
    use NumericGuardTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'bank_id', nullable: false, onDelete: 'RESTRICT')]
    private Bank $bank;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'currency_code', referencedColumnName: 'code', nullable: false, onDelete: 'RESTRICT')]
    private Currency $currency;

    #[ORM\Column(name: 'rate_date', type: 'datetime_immutable')]
    #[Assert\NotBlank(message: 'common.date_not_blank')]
    private \DateTimeImmutable $rateDate;

    #[ORM\Column(name: 'rate_to_pln_minor_units', type: 'bigint')]
    #[Assert\Range(notInRangeMessage: 'currency_exchange_rate.rate_out_of_range', min: 1, max: 999999)]
    private int $rateToPln;

    public function __construct(Bank $bank, Currency $currency, \DateTimeImmutable $rateDate, int $rateToPln)
    {
        $this->bank = $bank;
        $this->currency = $currency;
        $this->rateDate = $rateDate;
        $this->setRateToPln($rateToPln);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getBank(): Bank
    {
        return $this->bank;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function getRateDate(): \DateTimeImmutable
    {
        return $this->rateDate;
    }

    public function getRateToPln(): int
    {
        return $this->rateToPln;
    }

    public function setRateToPln(int $rateToPln): void
    {
        self::assertInRange($rateToPln, 1, 999999, 'Rate');
        $this->rateToPln = $rateToPln;
    }

    public function getRateToPlnAsMoney(): Money
    {
        return Money::fromMinorUnits(
            $this->rateToPln,
            Currencies::SETTLEMENT_CURRENCY_CODE,
            Currencies::EXCHANGE_RATE_SCALE
        );
    }
}
