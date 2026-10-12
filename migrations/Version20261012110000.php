<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Product attribute groups, attributes and product attribute values';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_product_attribute_group (
            id INT AUTO_INCREMENT NOT NULL,
            category_id INT DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            external_category_id INT DEFAULT NULL,
            position INT DEFAULT 0 NOT NULL,
            created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_shop_attr_group_slug (slug),
            UNIQUE INDEX uniq_shop_attr_group_external_category (external_category_id),
            INDEX IDX_ATTR_GROUP_CATEGORY (category_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE shop_product_attribute (
            id INT AUTO_INCREMENT NOT NULL,
            group_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            show_in_filter TINYINT(1) DEFAULT 1 NOT NULL,
            use_in_search TINYINT(1) DEFAULT 0 NOT NULL,
            data_type VARCHAR(16) NOT NULL,
            position INT DEFAULT 0 NOT NULL,
            created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_shop_product_attribute_slug (group_id, slug),
            INDEX IDX_ATTR_GROUP (group_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE shop_product_attribute_value (
            id INT AUTO_INCREMENT NOT NULL,
            product_id INT NOT NULL,
            attribute_id INT NOT NULL,
            value_varchar VARCHAR(255) DEFAULT NULL,
            value_text LONGTEXT DEFAULT NULL,
            value_int INT DEFAULT NULL,
            value_decimal NUMERIC(16, 4) DEFAULT NULL,
            created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_shop_product_attribute_value (product_id, attribute_id),
            INDEX idx_shop_pav_varchar (attribute_id, value_varchar),
            INDEX idx_shop_pav_int (attribute_id, value_int),
            INDEX IDX_PAV_PRODUCT (product_id),
            INDEX IDX_PAV_ATTRIBUTE (attribute_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE shop_product_attribute_group ADD CONSTRAINT FK_ATTR_GROUP_CATEGORY FOREIGN KEY (category_id) REFERENCES shop_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE shop_product_attribute ADD CONSTRAINT FK_ATTR_GROUP FOREIGN KEY (group_id) REFERENCES shop_product_attribute_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_product_attribute_value ADD CONSTRAINT FK_PAV_PRODUCT FOREIGN KEY (product_id) REFERENCES shop_product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_product_attribute_value ADD CONSTRAINT FK_PAV_ATTRIBUTE FOREIGN KEY (attribute_id) REFERENCES shop_product_attribute (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_product_attribute_value DROP FOREIGN KEY FK_PAV_PRODUCT');
        $this->addSql('ALTER TABLE shop_product_attribute_value DROP FOREIGN KEY FK_PAV_ATTRIBUTE');
        $this->addSql('ALTER TABLE shop_product_attribute DROP FOREIGN KEY FK_ATTR_GROUP');
        $this->addSql('ALTER TABLE shop_product_attribute_group DROP FOREIGN KEY FK_ATTR_GROUP_CATEGORY');
        $this->addSql('DROP TABLE shop_product_attribute_value');
        $this->addSql('DROP TABLE shop_product_attribute');
        $this->addSql('DROP TABLE shop_product_attribute_group');
    }
}
