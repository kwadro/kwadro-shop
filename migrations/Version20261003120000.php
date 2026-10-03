<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shop_redirect table for Symfony-managed URL redirects';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('shop_redirect')) {
            return;
        }

        $this->addSql('CREATE TABLE shop_redirect (
            id INT AUTO_INCREMENT NOT NULL,
            type VARCHAR(16) DEFAULT \'base\' NOT NULL,
            from_path VARCHAR(512) NOT NULL,
            to_path VARCHAR(2048) DEFAULT NULL,
            enabled TINYINT(1) DEFAULT 1 NOT NULL,
            status_code INT DEFAULT 301 NOT NULL,
            created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_shop_redirect_from_path (from_path),
            INDEX idx_shop_redirect_type_enabled (type, enabled),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_redirect')) {
            $this->addSql('DROP TABLE shop_redirect');
        }
    }
}
