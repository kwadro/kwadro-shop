<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shop email template section table';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('shop_email_template_section')) {
            return;
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE shop_email_template_section (
                id INT AUTO_INCREMENT NOT NULL,
                site_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                content LONGTEXT NOT NULL,
                created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_EMAIL_TEMPLATE_SECTION_SITE (site_id),
                UNIQUE INDEX uniq_email_template_section_site_name (site_id, name),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql('ALTER TABLE shop_email_template_section ADD CONSTRAINT FK_EMAIL_TEMPLATE_SECTION_SITE FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_template_section')) {
            return;
        }

        $this->addSql('ALTER TABLE shop_email_template_section DROP FOREIGN KEY FK_EMAIL_TEMPLATE_SECTION_SITE');
        $this->addSql('DROP TABLE shop_email_template_section');
    }
}
