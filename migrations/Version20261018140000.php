<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Shop mega menu settings: auto/manual mode + ordered category items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_mega_menu_setting (
            id INT AUTO_INCREMENT NOT NULL,
            site_id INT NOT NULL,
            mode VARCHAR(20) DEFAULT \'auto\' NOT NULL,
            UNIQUE INDEX uniq_shop_mega_menu_setting_site (site_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE shop_mega_menu_item (
            id INT AUTO_INCREMENT NOT NULL,
            menu_setting_id INT NOT NULL,
            category_id INT NOT NULL,
            enabled TINYINT(1) DEFAULT 1 NOT NULL,
            position INT DEFAULT 0 NOT NULL,
            INDEX IDX_SMMI_SETTING (menu_setting_id),
            INDEX IDX_SMMI_CATEGORY (category_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE shop_mega_menu_setting ADD CONSTRAINT FK_SMMS_SITE FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_mega_menu_item ADD CONSTRAINT FK_SMMI_SETTING FOREIGN KEY (menu_setting_id) REFERENCES shop_mega_menu_setting (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_mega_menu_item ADD CONSTRAINT FK_SMMI_CATEGORY FOREIGN KEY (category_id) REFERENCES shop_category (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_mega_menu_item DROP FOREIGN KEY FK_SMMI_SETTING');
        $this->addSql('ALTER TABLE shop_mega_menu_item DROP FOREIGN KEY FK_SMMI_CATEGORY');
        $this->addSql('ALTER TABLE shop_mega_menu_setting DROP FOREIGN KEY FK_SMMS_SITE');
        $this->addSql('DROP TABLE shop_mega_menu_item');
        $this->addSql('DROP TABLE shop_mega_menu_setting');
    }
}
