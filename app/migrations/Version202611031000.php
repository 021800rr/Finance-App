<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202611031000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make the database enforce the operation amount range, the business date range and that an operation '
            .'uses the currency of its account';
    }

    public function up(Schema $schema): void
    {
        // The same limits as AccountOperation and BusinessDateRange, written out: a migration must not change
        // when the code changes later.
        $this->addSql('ALTER TABLE account_operation ADD CONSTRAINT chk_account_operation_amount_range '
            .'CHECK (amount_minor_units BETWEEN -99999999 AND 99999999)');
        $this->addSql('ALTER TABLE account_operation ADD CONSTRAINT chk_account_operation_date_range '
            ."CHECK (operation_date BETWEEN '2025-01-01' AND '2070-12-31')");
        $this->addSql('ALTER TABLE gold_price ADD CONSTRAINT chk_gold_price_date_range '
            ."CHECK (price_date BETWEEN '2025-01-01' AND '2070-12-31')");
        $this->addSql('ALTER TABLE currency_exchange_rate ADD CONSTRAINT chk_currency_exchange_rate_date_range '
            ."CHECK (rate_date BETWEEN '2025-01-01' AND '2070-12-31')");

        // A CHECK cannot look into another table, and Doctrine cannot map a foreign key on (account_id, currency_code),
        // so every schema comparison would try to drop one. Triggers stay outside that comparison, like updated_at.
        $this->addSql(<<<'SQL'
            CREATE FUNCTION account_operation_currency_matches_account() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.currency_code IS DISTINCT FROM (SELECT currency_code FROM account WHERE id = NEW.account_id) THEN
                    RAISE EXCEPTION 'The currency of account operation % must be the currency of account %.',
                        NEW.id, NEW.account_id
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END
            $$
            SQL);
        $this->addSql('CREATE TRIGGER trg_account_operation_currency '
            .'BEFORE INSERT OR UPDATE OF account_id, currency_code ON account_operation '
            .'FOR EACH ROW EXECUTE FUNCTION account_operation_currency_matches_account()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER trg_account_operation_currency ON account_operation');
        $this->addSql('DROP FUNCTION account_operation_currency_matches_account()');
        $this->addSql('ALTER TABLE currency_exchange_rate DROP CONSTRAINT chk_currency_exchange_rate_date_range');
        $this->addSql('ALTER TABLE gold_price DROP CONSTRAINT chk_gold_price_date_range');
        $this->addSql('ALTER TABLE account_operation DROP CONSTRAINT chk_account_operation_date_range');
        $this->addSql('ALTER TABLE account_operation DROP CONSTRAINT chk_account_operation_amount_range');
    }
}
