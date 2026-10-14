<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add facebook_id to app_user and allow passwordless OAuth users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD facebook_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_APP_USER_FACEBOOK_ID ON app_user (facebook_id)');
        $this->addSql('ALTER TABLE app_user CHANGE password password VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_APP_USER_FACEBOOK_ID ON app_user');
        $this->addSql('ALTER TABLE app_user DROP facebook_id');
        $this->addSql('ALTER TABLE app_user CHANGE password password VARCHAR(255) NOT NULL');
    }
}
