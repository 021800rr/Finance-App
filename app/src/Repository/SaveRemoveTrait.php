<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\Entity\FixedExpense;
use App\Entity\GoldAsset;
use App\Entity\GoldPrice;
use App\Entity\User;

trait SaveRemoveTrait
{
    public function save(
        Account|AccountOperation|Contractor|Currency|CurrencyExchangeRate|FixedExpense|GoldAsset|GoldPrice|User $entity,
        bool $flush = false,
    ): void {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(
        Account|AccountOperation|Contractor|Currency|CurrencyExchangeRate|FixedExpense|GoldAsset|GoldPrice|User $entity,
        bool $flush = false,
    ): void {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
