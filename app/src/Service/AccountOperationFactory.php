<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use App\Form\FormData;
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
        $account = FormData::entity($form, 'account', Account::class);
        $currency = $account->getCurrency();

        return new AccountOperation(
            $account,
            $contractor,
            FormData::date($form, 'operationDate'),
            $this->amount($form, $currency->getCode()),
            $currency,
            description: FormData::optionalText($form, 'description'),
            confirmed: (bool) $form->get('confirmed')->getData(),
        );
    }

    private function updateFromForm(
        AccountOperation $operation,
        FormInterface $form,
        Contractor $contractor,
    ): AccountOperation {
        $operation->setOperationDate(FormData::date($form, 'operationDate'));
        $operation->setAmount($this->amount($form, $operation->getCurrency()->getCode()));
        $operation->setDescription(FormData::optionalText($form, 'description'));
        $operation->setConfirmed((bool) $form->get('confirmed')->getData());
        $operation->setContractor($contractor);

        return $operation;
    }

    private function amount(FormInterface $form, string $currencyCode): int
    {
        return Money::fromDecimalString(FormData::decimal($form, 'amount'), $currencyCode)->getMinorUnits();
    }
}
