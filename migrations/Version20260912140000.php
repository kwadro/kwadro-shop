<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shop email template table';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('shop_email_template')) {
            return;
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE shop_email_template (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                type VARCHAR(16) DEFAULT 'html' NOT NULL,
                content LONGTEXT NOT NULL,
                additional_css LONGTEXT DEFAULT NULL,
                created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_EMAIL_TEMPLATE_NAME (name),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_email_template')) {
            $this->addSql('DROP TABLE shop_email_template');
        }
    }
}
