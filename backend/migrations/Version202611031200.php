<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202611031200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index operations, gold prices and exchange rates in the order the lists and the latest-quote queries '
            .'read them';
    }

    public function up(Schema $schema): void
    {
        // Each new index starts with the column of the index it replaces, so the foreign keys stay covered.
        // The account history: WHERE account_id = ? ORDER BY operation_date DESC, id DESC.
        $this->addSql('DROP INDEX idx_account_operation_account');
        $this->addSql('CREATE INDEX idx_account_operation_account_date '
            .'ON account_operation (account_id, operation_date DESC, id DESC)');

        // LatestQuoteQuery: DISTINCT ON (group) ... ORDER BY group, date DESC, id DESC.
        $this->addSql('DROP INDEX idx_gold_price_gold_asset');
        $this->addSql('CREATE INDEX idx_gold_price_asset_date ON gold_price (gold_asset_id, price_date DESC, id DESC)');
        $this->addSql('DROP INDEX idx_currency_exchange_rate_bank');
        $this->addSql('CREATE INDEX idx_currency_exchange_rate_bank_date '
            .'ON currency_exchange_rate (bank_id, currency_code, rate_date DESC, id DESC)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_currency_exchange_rate_bank_date');
        $this->addSql('CREATE INDEX idx_currency_exchange_rate_bank ON currency_exchange_rate (bank_id)');
        $this->addSql('DROP INDEX idx_gold_price_asset_date');
        $this->addSql('CREATE INDEX idx_gold_price_gold_asset ON gold_price (gold_asset_id)');
        $this->addSql('DROP INDEX idx_account_operation_account_date');
        $this->addSql('CREATE INDEX idx_account_operation_account ON account_operation (account_id)');
    }
}
