<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shop_blocked_ip table for IP blocking';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('shop_blocked_ip')) {
            return;
        }

        $this->addSql('CREATE TABLE shop_blocked_ip (
            id INT AUTO_INCREMENT NOT NULL,
            ip VARCHAR(45) NOT NULL,
            note VARCHAR(255) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1 NOT NULL,
            created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_shop_blocked_ip (ip),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_blocked_ip')) {
            $this->addSql('DROP TABLE shop_blocked_ip');
        }
    }
}
