<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202610260900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store account operation, gold price and currency exchange rate dates without time';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account_operation ALTER operation_date TYPE DATE USING operation_date::date');
        $this->addSql('ALTER TABLE gold_price ALTER price_date TYPE DATE USING price_date::date');
        $this->addSql('ALTER TABLE currency_exchange_rate ALTER rate_date TYPE DATE USING rate_date::date');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account_operation ALTER operation_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE gold_price ALTER price_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE currency_exchange_rate ALTER rate_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
    }
}
