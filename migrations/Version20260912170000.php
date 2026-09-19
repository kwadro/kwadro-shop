<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shop order email table';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('shop_order_email')) {
            return;
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE shop_order_email (
                id INT AUTO_INCREMENT NOT NULL,
                template_id INT NOT NULL,
                sender_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                code VARCHAR(64) NOT NULL,
                created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_ORDER_EMAIL_CODE (code),
                INDEX IDX_ORDER_EMAIL_TEMPLATE (template_id),
                INDEX IDX_ORDER_EMAIL_SENDER (sender_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql('ALTER TABLE shop_order_email ADD CONSTRAINT FK_ORDER_EMAIL_TEMPLATE FOREIGN KEY (template_id) REFERENCES shop_email_template (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE shop_order_email ADD CONSTRAINT FK_ORDER_EMAIL_SENDER FOREIGN KEY (sender_id) REFERENCES shop_email_sender (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('shop_order_email')) {
            return;
        }

        $this->addSql('ALTER TABLE shop_order_email DROP FOREIGN KEY FK_ORDER_EMAIL_TEMPLATE');
        $this->addSql('ALTER TABLE shop_order_email DROP FOREIGN KEY FK_ORDER_EMAIL_SENDER');
        $this->addSql('DROP TABLE shop_order_email');
    }
}
