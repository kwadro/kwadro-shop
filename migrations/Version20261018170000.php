<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Homepage category items: ordered category + position (like mega menu)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_homepage_category_item (
            id INT AUTO_INCREMENT NOT NULL,
            homepage_setting_id INT NOT NULL,
            category_id INT NOT NULL,
            enabled TINYINT(1) DEFAULT 1 NOT NULL,
            position INT DEFAULT 0 NOT NULL,
            INDEX IDX_HCI_SETTING (homepage_setting_id),
            INDEX IDX_HCI_CATEGORY (category_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE shop_homepage_category_item ADD CONSTRAINT FK_HCI_SETTING FOREIGN KEY (homepage_setting_id) REFERENCES shop_homepage_setting (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_homepage_category_item ADD CONSTRAINT FK_HCI_CATEGORY FOREIGN KEY (category_id) REFERENCES shop_category (id) ON DELETE CASCADE');

        // Migrate existing ManyToMany selections (order by category_id as initial position).
        $this->addSql('INSERT INTO shop_homepage_category_item (homepage_setting_id, category_id, enabled, position)
            SELECT homepage_setting_id, category_id, 1, category_id
            FROM shop_homepage_setting_category');

        $this->addSql('ALTER TABLE shop_homepage_setting_category DROP FOREIGN KEY FK_HSC_SETTING');
        $this->addSql('ALTER TABLE shop_homepage_setting_category DROP FOREIGN KEY FK_HSC_CATEGORY');
        $this->addSql('DROP TABLE shop_homepage_setting_category');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_homepage_setting_category (
            homepage_setting_id INT NOT NULL,
            category_id INT NOT NULL,
            INDEX IDX_HSC_SETTING (homepage_setting_id),
            INDEX IDX_HSC_CATEGORY (category_id),
            PRIMARY KEY(homepage_setting_id, category_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE shop_homepage_setting_category ADD CONSTRAINT FK_HSC_SETTING FOREIGN KEY (homepage_setting_id) REFERENCES shop_homepage_setting (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_homepage_setting_category ADD CONSTRAINT FK_HSC_CATEGORY FOREIGN KEY (category_id) REFERENCES shop_category (id) ON DELETE CASCADE');
        $this->addSql('INSERT INTO shop_homepage_setting_category (homepage_setting_id, category_id)
            SELECT homepage_setting_id, category_id FROM shop_homepage_category_item');
        $this->addSql('ALTER TABLE shop_homepage_category_item DROP FOREIGN KEY FK_HCI_SETTING');
        $this->addSql('ALTER TABLE shop_homepage_category_item DROP FOREIGN KEY FK_HCI_CATEGORY');
        $this->addSql('DROP TABLE shop_homepage_category_item');
    }
}
