<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class TransactionService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @return array{out: AccountOperation, in: AccountOperation}
     *
     * @throws \Throwable
     */
    public function transferBetweenAccounts(
        Account $from,
        Account $to,
        Contractor $contractor,
        \DateTimeImmutable $date,
        string $amountFrom,
        ?string $amountTo,
        ?string $description,
    ): array {
        if ($from === $to) {
            throw new \InvalidArgumentException('Cannot transfer between the same account.');
        }

        $fromCurrencyCode = $from->getCurrency()->getCode();
        $toCurrencyCode = $to->getCurrency()->getCode();

        if ($fromCurrencyCode !== $toCurrencyCode && null === $amountTo) {
            throw new \InvalidArgumentException('For different currencies amountTo is required.');
        }

        $debitMoney = Money::fromDecimalString($amountFrom, $fromCurrencyCode);

        if (!$debitMoney->isPositive()) {
            throw new \InvalidArgumentException('Transfer amounts must be positive.');
        }

        $creditMoney = null !== $amountTo
            ? Money::fromDecimalString($amountTo, $toCurrencyCode)
            : Money::fromDecimalString($amountFrom, $toCurrencyCode);

        if (!$creditMoney->isPositive()) {
            throw new \InvalidArgumentException('Transfer amounts must be positive.');
        }

        /** @var array{out: AccountOperation, in: AccountOperation} $result */
        $result = $this->em->wrapInTransaction(function () use (
            $from,
            $to,
            $contractor,
            $date,
            $debitMoney,
            $creditMoney,
            $description,
        ): array {
            $transactionUuid = Uuid::v7();

            $opOut = new AccountOperation(
                $from,
                $contractor,
                $date,
                $debitMoney->negate()->getMinorUnits(),
                $from->getCurrency(),
                description: $description,
                transactionUuid: $transactionUuid,
            );

            $this->em->persist($opOut);

            $opIn = new AccountOperation(
                $to,
                $contractor,
                $date,
                $creditMoney->getMinorUnits(),
                $to->getCurrency(),
                description: $description,
                transactionUuid: $transactionUuid,
            );

            $this->em->persist($opIn);

            return [
                'out' => $opOut,
                'in' => $opIn,
            ];
        });

        return $result;
    }
}
