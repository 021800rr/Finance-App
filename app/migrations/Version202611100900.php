<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202611100900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Let each account have its own place in the summary list';
    }

    public function up(Schema $schema): void
    {
        // Set by hand: lower numbers first; the accounts without one come after them, by account number.
        $this->addSql('ALTER TABLE account ADD sort_order INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account DROP sort_order');
    }
}
