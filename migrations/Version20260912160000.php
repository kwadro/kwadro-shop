<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shop email sender table';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('shop_email_sender')) {
            return;
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE shop_email_sender (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_EMAIL_SENDER_EMAIL (email),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_email_sender')) {
            $this->addSql('DROP TABLE shop_email_sender');
        }
    }
}
