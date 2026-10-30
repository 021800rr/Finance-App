<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\Contractors;
use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use App\Repository\ContractorRepository;
use App\Util\OptionalText;
use Symfony\Component\Form\FormInterface;

final readonly class TransferHandler
{
    public function __construct(
        private TransactionService $transactionService,
        private ContractorRepository $contractorRepository,
    ) {
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
            $this->resolveFixedContractor(),
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

    // A transfer always belongs to the fixed self-transfer contractor; the form does not offer a choice.
    private function resolveFixedContractor(): Contractor
    {
        return $this->contractorRepository->findOrCreateByName(Contractors::SELF_TRANSFER_NAME);
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

        return $amount;
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

        return OptionalText::normalize(is_string($description) ? $description : null);
    }
}
