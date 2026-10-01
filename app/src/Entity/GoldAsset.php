<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\BlankStringGuardTrait;
use App\Entity\Trait\NumericGuardTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\GoldAssetRepository;
use App\ValueObject\Quantity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GoldAssetRepository::class)]
#[ORM\Table(name: 'gold_asset')]
#[ORM\Index(name: 'idx_gold_asset_gold_form', columns: ['gold_form_id'])]
class GoldAsset
{
    use TimestampTrait;
    use NumericGuardTrait;
    use BlankStringGuardTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'gold_form_id', nullable: false, onDelete: 'RESTRICT')]
    private GoldForm $form;

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'gold_asset.description_not_blank')]
    private string $description;

    #[ORM\Column(name: 'ounces_scaled', type: 'bigint')]
    #[Assert\Range(notInRangeMessage: 'gold_asset.ounces_out_of_range', min: 1, max: 9999999)]
    private int $ounces;

    public function __construct(GoldForm $form, int $ounces, string $description)
    {
        $this->form = $form;
        $this->setOunces($ounces);
        $this->setDescription($description);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getForm(): GoldForm
    {
        return $this->form;
    }

    public function setForm(GoldForm $form): void
    {
        $this->form = $form;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        self::assertNotBlank($description, 'Description');
        $this->description = $description;
    }

    public function getOunces(): int
    {
        return $this->ounces;
    }

    public function setOunces(int $ounces): void
    {
        self::assertInRange($ounces, 1, 9999999, 'Ounces');
        $this->ounces = $ounces;
    }

    public function getOuncesAsQuantity(): Quantity
    {
        return Quantity::fromMinorUnits($this->ounces);
    }
}
