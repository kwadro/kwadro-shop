<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260830181627 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE shop_cart (id INT AUTO_INCREMENT NOT NULL, visitor_id VARCHAR(36) DEFAULT NULL, cart_data JSON DEFAULT NULL, delivery_data JSON DEFAULT NULL, order_data JSON DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, customer_id INT DEFAULT NULL, UNIQUE INDEX uniq_cart_visitor_id (visitor_id), UNIQUE INDEX uniq_cart_customer_id (customer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE shop_cart ADD CONSTRAINT FK_CA516ECC9395C3F3 FOREIGN KEY (customer_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_user CHANGE roles roles JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE shop_cart DROP FOREIGN KEY FK_CA516ECC9395C3F3');
        $this->addSql('DROP TABLE shop_cart');
        $this->addSql('ALTER TABLE app_user CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
    }
}
