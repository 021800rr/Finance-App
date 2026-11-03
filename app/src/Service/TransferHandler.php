<?php

declare(strict_types=1);

namespace App\Service;

use App\Constant\Contractors;
use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use App\Form\FormData;
use App\Repository\ContractorRepository;
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
            FormData::entity($form, 'fromAccount', Account::class),
            FormData::entity($form, 'toAccount', Account::class),
            $this->resolveFixedContractor(),
            FormData::date($form, 'date'),
            FormData::decimal($form, 'amountFrom'),
            FormData::optionalDecimal($form, 'amountTo'),
            FormData::optionalText($form, 'description')
        );
    }

    // A transfer always belongs to the fixed self-transfer contractor; the form does not offer a choice.
    private function resolveFixedContractor(): Contractor
    {
        return $this->contractorRepository->findOrCreateByName(Contractors::SELF_TRANSFER_NAME);
    }
}
