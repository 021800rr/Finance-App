<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use Symfony\Component\Form\FormInterface;

final readonly class TransferHandler
{
    public function __construct(private TransactionService $transactionService)
    {
    }

    /**
     * @return array{out: AccountOperation, in: AccountOperation}
     *
     * @throws \Throwable
     */
    public function handle(FormInterface $form): array
    {
        return $this->transactionService->transferBetweenAccounts(
            $this->extractAccount($form, 'fromAccount'),
            $this->extractAccount($form, 'toAccount'),
            $this->extractContractor($form),
            $this->extractDate($form),
            $this->extractAmountFrom($form),
            $this->extractOptionalAmountTo($form),
            $this->extractDescription($form)
        );
    }

    private function extractAccount(FormInterface $form, string $field): Account
    {
        $account = $form->get($field)->getData();
        if (!$account instanceof Account) {
            throw new \InvalidArgumentException(sprintf('%s is required.', $field));
        }

        return $account;
    }

    private function extractContractor(FormInterface $form): Contractor
    {
        $contractor = $form->get('contractor')->getData();
        if (!$contractor instanceof Contractor) {
            throw new \InvalidArgumentException('Contractor is required.');
        }

        return $contractor;
    }

    private function extractDate(FormInterface $form): \DateTimeImmutable
    {
        $date = $form->get('date')->getData();
        if (!$date instanceof \DateTimeImmutable) {
            throw new \InvalidArgumentException('Date is required.');
        }

        return $date;
    }

    private function extractAmountFrom(FormInterface $form): string
    {
        $amount = $form->get('amountFrom')->getData();
        if (!is_string($amount)) {
            throw new \InvalidArgumentException('Amount must be a numeric value.');
        }

        return (string) $amount;
    }

    private function extractOptionalAmountTo(FormInterface $form): ?string
    {
        $amount = $form->get('amountTo')->getData();
        if (null === $amount) {
            return null;
        }

        if (!is_string($amount)) {
            throw new \InvalidArgumentException('Amount must be a numeric value.');
        }

        return (string) $amount;
    }

    private function extractDescription(FormInterface $form): ?string
    {
        $description = $form->get('description')->getData();

        return is_string($description) && '' !== trim($description) ? $description : null;
    }
}
