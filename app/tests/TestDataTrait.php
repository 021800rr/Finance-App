<?php

declare(strict_types=1);

namespace App\Tests;

use App\Constant\Currencies;
use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\AccountType;
use App\Entity\Bank;
use App\Entity\Contractor;
use App\Entity\Currency;
use App\Entity\CurrencyExchangeRate;
use App\Entity\FixedExpense;
use App\Entity\GoldAsset;
use App\Entity\GoldForm;
use App\Entity\GoldPrice;
use App\Entity\Household;
use App\Repository\AccountRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Creates the records a test needs on top of the fixtures. Every create* method persists and flushes, so the record
 * already has its id, and gives a unique name to whatever is not passed. The test transaction rolls it all back.
 * Requires DatabaseTestTrait (the em() method).
 */
trait TestDataTrait
{
    /**
     * A currency from the fixtures (PLN or CHF).
     */
    private function currency(string $code = Currencies::SETTLEMENT_CURRENCY_CODE): Currency
    {
        return $this->em()->find(Currency::class, $code)
            ?? throw new \LogicException(sprintf('The fixtures have no %s currency.', $code));
    }

    /**
     * A household from the fixtures: the one with own funds or the other one.
     */
    private function household(bool $ownFunds = true): Household
    {
        return $this->em()->getRepository(Household::class)->findOneBy(['isOwnFunds' => $ownFunds])
            ?? throw new \LogicException('The fixtures have no such household.');
    }

    /**
     * An account by its number, e.g. "Bank1-ROR-PLN" from the fixtures.
     */
    private function account(string $accountNumber): Account
    {
        return $this->em()->getRepository(Account::class)->findOneBy(['accountNumber' => $accountNumber])
            ?? throw new \LogicException(sprintf('There is no account "%s".', $accountNumber));
    }

    /**
     * A gold form from the fixtures ("moneta" or "sztabka").
     */
    private function goldForm(string $name): GoldForm
    {
        return $this->em()->getRepository(GoldForm::class)->findOneBy(['name' => $name])
            ?? throw new \LogicException(sprintf('The fixtures have no gold form "%s".', $name));
    }

    /**
     * A currency the fixtures do not have, e.g. EUR.
     */
    private function createCurrency(string $code): Currency
    {
        return $this->saved(new Currency($code));
    }

    private function createBank(?string $name = null): Bank
    {
        return $this->saved(new Bank($name ?? 'Test Bank '.uniqid()));
    }

    private function createAccountType(?string $name = null, bool $retirement = false): AccountType
    {
        return $this->saved(new AccountType($name ?? 'Test Type '.uniqid(), $retirement));
    }

    /**
     * By default a PLN account of the own-funds household, in a new bank and of a new type. With $main it becomes the
     * main account in place of the one from the fixtures.
     */
    private function createAccount(
        string|Currency $currency = Currencies::SETTLEMENT_CURRENCY_CODE,
        ?Bank $bank = null,
        ?AccountType $type = null,
        ?Household $household = null,
        ?string $accountNumber = null,
        bool $hidden = false,
        bool $main = false,
    ): Account {
        $account = new Account(
            $bank ?? $this->createBank(),
            is_string($currency) ? $this->currency($currency) : $currency,
            $type ?? $this->createAccountType(),
            $accountNumber ?? 'ACC-'.uniqid(),
            $household ?? $this->household(),
        );
        $account->setHidden($hidden);
        $this->saved($account);

        if ($main) {
            /** @var AccountRepository $accounts */
            $accounts = $this->em()->getRepository(Account::class);
            $accounts->setAsMainAccount($account);
        }

        return $account;
    }

    private function createContractor(?string $name = null): Contractor
    {
        return $this->saved(new Contractor($name ?? 'Test Contractor '.uniqid()));
    }

    /**
     * An operation in the account's currency, by default dated today and with a new contractor.
     */
    private function createOperation(
        Account $account,
        int $amount = 1000,
        ?\DateTimeImmutable $date = null,
        ?Contractor $contractor = null,
        ?string $description = null,
        bool $confirmed = false,
        ?Uuid $transactionUuid = null,
    ): AccountOperation {
        return $this->saved(new AccountOperation(
            $account,
            $contractor ?? $this->createContractor(),
            $date ?? new \DateTimeImmutable(),
            $amount,
            $account->getCurrency(),
            description: $description,
            confirmed: $confirmed,
            transactionUuid: $transactionUuid,
        ));
    }

    /**
     * By default a CHF rate of today, in a new bank.
     */
    private function createRate(
        ?Bank $bank = null,
        string|Currency $currency = 'CHF',
        ?\DateTimeImmutable $date = null,
        int $rateToPln = 43210,
    ): CurrencyExchangeRate {
        return $this->saved(new CurrencyExchangeRate(
            $bank ?? $this->createBank(),
            is_string($currency) ? $this->currency($currency) : $currency,
            $date ?? new \DateTimeImmutable(),
            $rateToPln,
        ));
    }

    private function createGoldForm(?string $name = null): GoldForm
    {
        return $this->saved(new GoldForm($name ?? 'Test Form '.uniqid()));
    }

    private function createGoldAsset(
        ?GoldForm $form = null,
        int $ounces = 10000,
        string $description = 'Test',
    ): GoldAsset {
        return $this->saved(new GoldAsset($form ?? $this->createGoldForm(), $ounces, $description));
    }

    /**
     * By default a PLN price of today.
     */
    private function createGoldPrice(
        GoldAsset $asset,
        ?\DateTimeImmutable $date = null,
        int $pricePln = 100000,
    ): GoldPrice {
        return $this->saved(new GoldPrice(
            $asset,
            $date ?? new \DateTimeImmutable(),
            $pricePln,
            $this->currency(),
        ));
    }

    /**
     * By default a PLN expense on the 10th, with a new contractor.
     */
    private function createFixedExpense(
        int $day = 10,
        int $amount = -15000,
        ?Contractor $contractor = null,
        ?string $description = null,
    ): FixedExpense {
        $fixedExpense = new FixedExpense(
            $contractor ?? $this->createContractor(),
            $day,
            $amount,
            $this->currency(),
        );
        $fixedExpense->setDescription($description);

        return $this->saved($fixedExpense);
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function saved(object $entity): object
    {
        $this->em()->persist($entity);
        $this->em()->flush();

        return $entity;
    }
}
