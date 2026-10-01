<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final readonly class AccountBalanceReportRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return list<array{
     *     id: int,
     *     bank: string,
     *     type: string,
     *     currency: string,
     *     account_number: string,
     *     hidden: bool,
     *     household: string,
     *     is_own_funds: bool,
     *     is_retirement: bool,
     *     saldo: numeric-string
     * }>
     *
     * @throws Exception
     */
    public function fetchAccountBalances(): array
    {
        /** @var list<array{
         *      id: int,
         *      bank: string,
         *      type: string,
         *      currency: string,
         *      account_number: string,
         *      hidden: bool,
         *      household: string,
         *      is_own_funds: bool,
         *      is_retirement: bool,
         *      saldo: numeric-string
         * }> $result
         */
        $result = $this->connection->fetchAllAssociative(
            <<<SQL
            SELECT
                a.id,
                b.name AS bank,
                at.name AS type,
                c.code AS currency,
                a.account_number,
                a.hidden,
                h.name AS household,
                h.is_own_funds,
                at.is_retirement,
                COALESCE(SUM(o.amount_minor_units), 0) AS saldo
            FROM account a
                    JOIN bank b ON b.id = a.bank_id
                    JOIN account_type at ON at.id = a.account_type_id
                    JOIN currency c ON c.code = a.currency_code
                    JOIN household h ON h.id = a.household_id
                    LEFT JOIN account_operation o ON o.account_id = a.id
            GROUP BY a.id, b.name, at.name, c.code, a.account_number, a.hidden, h.name, h.is_own_funds, at.is_retirement
            ORDER BY b.name, at.name, c.code, a.account_number
            SQL
        );

        return $result;
    }
}
