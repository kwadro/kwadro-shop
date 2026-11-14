<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'User group catalog discount percent; user invitation token fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE app_user_group ADD discount_percent NUMERIC(5, 2) DEFAULT '0.00' NOT NULL");
        $this->addSql("UPDATE app_user_group SET discount_percent = '5.00' WHERE code = 'client'");

        $this->addSql('ALTER TABLE app_user ADD invitation_token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD invitation_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_APP_USER_INVITATION_TOKEN ON app_user (invitation_token_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_APP_USER_INVITATION_TOKEN ON app_user');
        $this->addSql('ALTER TABLE app_user DROP invitation_token_hash');
        $this->addSql('ALTER TABLE app_user DROP invitation_expires_at');
        $this->addSql('ALTER TABLE app_user_group DROP discount_percent');
    }
}
