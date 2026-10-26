<?php

declare(strict_types=1);

namespace App\Repository;

final class LatestQuoteQuery
{
    private function __construct()
    {
    }

    /**
     * Select the newest date, then highest ID, for each asset or bank/currency pair.
     * With perDay enabled, select a winner separately for every day in the history.
     *
     * @param literal-string                 $table
     * @param non-empty-list<literal-string> $groupColumns
     * @param literal-string                 $dateColumn
     */
    public static function build(
        string $table,
        array $groupColumns,
        string $dateColumn,
        bool $perDay = false,
    ): string {
        $group = implode(', ', $groupColumns);
        $distinct = $perDay ? $group.', '.$dateColumn : $group;

        return
            "SELECT DISTINCT ON ({$distinct}) * ".
            "FROM {$table} ".
            "ORDER BY {$group}, {$dateColumn} DESC, id DESC";
    }
}
