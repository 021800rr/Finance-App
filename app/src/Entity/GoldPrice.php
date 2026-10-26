<?php

declare(strict_types=1);

namespace App\Entity;

use App\Constant\Currencies;
use App\Entity\Trait\NumericGuardTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\GoldPriceRepository;
use App\Util\BusinessDateRange;
use App\Validator\BusinessDate;
use App\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GoldPriceRepository::class)]
#[ORM\Table(name: 'gold_price')]
#[ORM\Index(name: 'idx_gold_price_gold_asset', columns: ['gold_asset_id'])]
#[ORM\Index(name: 'idx_gold_price_currency', columns: ['currency_code'])]
class GoldPrice
{
    use TimestampTrait;
    use NumericGuardTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'gold_asset_id', nullable: false, onDelete: 'CASCADE')]
    private GoldAsset $goldAsset;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotBlank(message: 'common.date_not_blank')]
    #[BusinessDate]
    private \DateTimeImmutable $priceDate;

    #[ORM\Column(name: 'price_pln_minor_units', type: 'bigint')]
    #[Assert\Range(notInRangeMessage: 'gold_price.price_out_of_range', min: 1, max: 99999999)]
    private int $pricePln;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'currency_code', referencedColumnName: 'code', nullable: false, onDelete: 'RESTRICT')]
    private Currency $currency;

    public function __construct(
        GoldAsset $goldAsset,
        \DateTimeImmutable $priceDate,
        int $pricePln,
        Currency $currency,
    ) {
        BusinessDateRange::assertContains($priceDate);
        self::assertInRange($pricePln, 1, 99999999, 'Price');

        $this->goldAsset = $goldAsset;
        $this->priceDate = $priceDate->setTime(0, 0, 0);
        $this->pricePln = $pricePln;
        $this->currency = $currency;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getGoldAsset(): GoldAsset
    {
        return $this->goldAsset;
    }

    public function getPriceDate(): \DateTimeImmutable
    {
        return $this->priceDate;
    }

    public function getPricePln(): int
    {
        return $this->pricePln;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function getPricePlnAsMoney(): Money
    {
        return Money::fromMinorUnits($this->pricePln, $this->currency->getCode(), Currencies::MINOR_UNIT_SCALE);
    }
}
