<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002121725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the mandatory user name; existing users get the local part of their email.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_user ADD name VARCHAR(100) NOT NULL');
        $this->addSql("UPDATE identity_user SET name = SUBSTRING_INDEX(email, '@', 1) WHERE name = ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_user DROP name');
    }
}
