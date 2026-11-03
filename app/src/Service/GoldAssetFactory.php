<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Form\FormData;
use App\ValueObject\Quantity;
use Symfony\Component\Form\FormInterface;

final readonly class GoldAssetFactory
{
    public function createFromForm(FormInterface $form): GoldAsset
    {
        return new GoldAsset(
            FormData::entity($form, 'form', GoldForm::class),
            $this->ounces($form),
            $this->extractDescription($form)
        );
    }

    public function updateFromForm(GoldAsset $asset, FormInterface $form): void
    {
        $asset->setForm(FormData::entity($form, 'form', GoldForm::class));
        $asset->setOunces($this->ounces($form));
        $asset->setDescription($this->extractDescription($form));
    }

    private function ounces(FormInterface $form): int
    {
        return Quantity::fromDecimalString(FormData::decimal($form, 'ounces'))->getMinorUnits();
    }

    // The only required text the factories read, so it stays here rather than in FormData.
    private function extractDescription(FormInterface $form): string
    {
        $description = $form->get('description')->getData();
        if (!is_string($description) || '' === trim($description)) {
            throw new \InvalidArgumentException('Description is required.');
        }

        return $description;
    }
}
