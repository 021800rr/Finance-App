<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\ValueObject\Quantity;
use Symfony\Component\Form\FormInterface;

final readonly class GoldAssetFactory
{
    public function createFromForm(FormInterface $form): GoldAsset
    {
        return new GoldAsset(
            $this->extractGoldForm($form),
            $this->extractOuncesQuantity($form)->getMinorUnits(),
            $this->extractDescription($form)
        );
    }

    public function updateFromForm(GoldAsset $asset, FormInterface $form): void
    {
        $asset->setForm($this->extractGoldForm($form));
        $asset->setOunces($this->extractOuncesQuantity($form)->getMinorUnits());
        $asset->setDescription($this->extractDescription($form));
    }

    private function extractGoldForm(FormInterface $form): GoldForm
    {
        $goldForm = $form->get('form')->getData();
        if (!$goldForm instanceof GoldForm) {
            throw new \InvalidArgumentException('Gold form is required.');
        }

        return $goldForm;
    }

    private function extractOuncesQuantity(FormInterface $form): Quantity
    {
        $rawOunces = $form->get('ounces')->getData();
        if (!is_string($rawOunces)) {
            throw new \InvalidArgumentException('Ounces must be a numeric value.');
        }

        return Quantity::fromDecimalString($rawOunces);
    }

    private function extractDescription(FormInterface $form): string
    {
        $description = $form->get('description')->getData();
        if (!is_string($description) || '' === trim($description)) {
            throw new \InvalidArgumentException('Description is required.');
        }

        return $description;
    }
}
