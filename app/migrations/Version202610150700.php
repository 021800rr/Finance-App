<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202610150700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store login e-mail addresses trimmed and in lower case, matching User::normalizeEmail()';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE app_user SET email = LOWER(TRIM(email))');
    }

    public function down(Schema $schema): void
    {
        // The original capitalisation is not recoverable; lower case remains a valid login.
    }
}
