<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202611011900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Limit the day of a fixed expense to 1-28 in the database as well';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fixed_expense ADD CONSTRAINT chk_fixed_expense_day CHECK (day BETWEEN 1 AND 28)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fixed_expense DROP CONSTRAINT chk_fixed_expense_day');
    }
}
