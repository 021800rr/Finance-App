<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use App\Repository\AccountOperationRepository;
use App\ValueObject\Money;
use Symfony\Component\Form\FormInterface;

final readonly class AccountOperationFactory
{
    public function createOrUpdateAndPersist(
        ?AccountOperation $existingOperation,
        FormInterface $form,
        Contractor $contractor,
        AccountOperationRepository $repository,
    ): AccountOperation {
        $operation = null === $existingOperation
            ? $this->createFromForm($form, $contractor)
            : $this->updateFromForm($existingOperation, $form, $contractor);

        $repository->save($operation, flush: true);

        return $operation;
    }

    private function createFromForm(FormInterface $form, Contractor $contractor): AccountOperation
    {
        $account = $this->extractAccount($form);
        $money = $this->extractMoney($form, $account);

        $operation = new AccountOperation(
            $account,
            $contractor,
            $this->extractOperationDate($form),
            $money->getMinorUnits(),
            $account->getCurrency(),
        );
        $operation->setDescription($this->extractDescription($form));
        $operation->setConfirmed($this->extractConfirmed($form));

        return $operation;
    }

    private function updateFromForm(AccountOperation $operation, FormInterface $form, Contractor $contractor): AccountOperation
    {
        $this->applyFormData($operation, $form);
        $operation->setContractor($contractor);

        return $operation;
    }

    private function applyFormData(AccountOperation $operation, FormInterface $form): void
    {
        $account = $this->extractAccount($form);
        $money = $this->extractMoney($form, $account);

        $operation->setAccount($account);
        $operation->setCurrency($account->getCurrency());
        $operation->setOperationDate($this->extractOperationDate($form));
        $operation->setAmount($money->getMinorUnits());
        $operation->setDescription($this->extractDescription($form));
        $operation->setConfirmed($this->extractConfirmed($form));
    }

    private function extractAccount(FormInterface $form): Account
    {
        $account = $form->get('account')->getData();
        if (!$account instanceof Account) {
            throw new \InvalidArgumentException('Account is required.');
        }

        return $account;
    }

    private function extractOperationDate(FormInterface $form): \DateTimeImmutable
    {
        $operationDate = $form->get('operationDate')->getData();
        if (!$operationDate instanceof \DateTimeImmutable) {
            throw new \InvalidArgumentException('Operation date is required.');
        }

        return $operationDate;
    }

    private function extractMoney(FormInterface $form, Account $account): Money
    {
        $rawAmount = $form->get('amount')->getData();
        if (!is_string($rawAmount)) {
            throw new \InvalidArgumentException('Amount must be a numeric value.');
        }

        return Money::fromDecimalString($rawAmount, $account->getCurrency()->getCode());
    }

    private function extractDescription(FormInterface $form): ?string
    {
        $description = $form->get('description')->getData();

        return is_string($description) ? $description : null;
    }

    private function extractConfirmed(FormInterface $form): bool
    {
        return (bool) $form->get('confirmed')->getData();
    }
}
