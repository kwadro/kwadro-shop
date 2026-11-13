<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Product attributes become global (M2M with groups via group_item).
 */
final class Version20261113140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'EAV: many-to-many attribute ↔ group; unique attribute slug; unique group category';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_product_attribute_group_item (
            id INT AUTO_INCREMENT NOT NULL,
            group_id INT NOT NULL,
            attribute_id INT NOT NULL,
            position INT DEFAULT 0 NOT NULL,
            created_at DATETIME DEFAULT NULL,
            updated_at DATETIME DEFAULT NULL,
            UNIQUE INDEX uniq_shop_attr_group_item (group_id, attribute_id),
            INDEX IDX_ATTR_GROUP_ITEM_GROUP (group_id),
            INDEX IDX_ATTR_GROUP_ITEM_ATTR (attribute_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE shop_product_attribute_group_item ADD CONSTRAINT FK_ATTR_GROUP_ITEM_GROUP FOREIGN KEY (group_id) REFERENCES shop_product_attribute_group (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_product_attribute_group_item ADD CONSTRAINT FK_ATTR_GROUP_ITEM_ATTR FOREIGN KEY (attribute_id) REFERENCES shop_product_attribute (id) ON DELETE CASCADE');

        $this->addSql('INSERT INTO shop_product_attribute_group_item (group_id, attribute_id, position, created_at, updated_at)
            SELECT group_id, id, position, created_at, updated_at FROM shop_product_attribute');

        // Canonical attribute per slug = lowest id.
        $this->addSql('UPDATE shop_product_attribute_group_item i
            INNER JOIN shop_product_attribute a ON a.id = i.attribute_id
            INNER JOIN (
                SELECT slug, MIN(id) AS canonical_id
                FROM shop_product_attribute
                GROUP BY slug
            ) c ON c.slug = a.slug
            SET i.attribute_id = c.canonical_id
            WHERE i.attribute_id <> c.canonical_id');

        // Drop duplicate membership rows after remapping.
        $this->addSql('DELETE i1 FROM shop_product_attribute_group_item i1
            INNER JOIN shop_product_attribute_group_item i2
            ON i1.group_id = i2.group_id
            AND i1.attribute_id = i2.attribute_id
            AND i1.id > i2.id');

        // Point values at canonical attributes; drop collisions.
        $this->addSql('UPDATE shop_product_attribute_value v
            INNER JOIN shop_product_attribute a ON a.id = v.attribute_id
            INNER JOIN (
                SELECT slug, MIN(id) AS canonical_id
                FROM shop_product_attribute
                GROUP BY slug
            ) c ON c.slug = a.slug
            SET v.attribute_id = c.canonical_id
            WHERE v.attribute_id <> c.canonical_id');

        $this->addSql('DELETE v1 FROM shop_product_attribute_value v1
            INNER JOIN shop_product_attribute_value v2
            ON v1.product_id = v2.product_id
            AND v1.attribute_id = v2.attribute_id
            AND v1.id > v2.id');

        $this->addSql('DELETE a FROM shop_product_attribute a
            INNER JOIN (
                SELECT slug, MIN(id) AS canonical_id
                FROM shop_product_attribute
                GROUP BY slug
            ) c ON c.slug = a.slug
            WHERE a.id <> c.canonical_id');

        $this->addSql('ALTER TABLE shop_product_attribute DROP FOREIGN KEY FK_ATTR_GROUP');
        $this->addSql('DROP INDEX uniq_shop_product_attribute_slug ON shop_product_attribute');
        $this->addSql('ALTER TABLE shop_product_attribute DROP group_id, DROP position');
        $this->addSql('CREATE UNIQUE INDEX uniq_shop_product_attribute_slug ON shop_product_attribute (slug)');

        // Ensure at most one group per category (keep lowest id).
        $this->addSql('UPDATE shop_product_attribute_group g
            INNER JOIN (
                SELECT category_id, MIN(id) AS keep_id
                FROM shop_product_attribute_group
                WHERE category_id IS NOT NULL
                GROUP BY category_id
                HAVING COUNT(*) > 1
            ) d ON d.category_id = g.category_id AND g.id <> d.keep_id
            SET g.category_id = NULL');

        $this->addSql('CREATE UNIQUE INDEX uniq_shop_attr_group_category ON shop_product_attribute_group (category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_shop_attr_group_category ON shop_product_attribute_group');

        $this->addSql('ALTER TABLE shop_product_attribute ADD group_id INT DEFAULT NULL, ADD position INT DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE shop_product_attribute a
            INNER JOIN (
                SELECT attribute_id, MIN(group_id) AS group_id, MIN(position) AS position
                FROM shop_product_attribute_group_item
                GROUP BY attribute_id
            ) i ON i.attribute_id = a.id
            SET a.group_id = i.group_id, a.position = i.position');
        $this->addSql('DELETE FROM shop_product_attribute WHERE group_id IS NULL');
        $this->addSql('ALTER TABLE shop_product_attribute MODIFY group_id INT NOT NULL');
        $this->addSql('DROP INDEX uniq_shop_product_attribute_slug ON shop_product_attribute');
        $this->addSql('CREATE UNIQUE INDEX uniq_shop_product_attribute_slug ON shop_product_attribute (group_id, slug)');
        $this->addSql('ALTER TABLE shop_product_attribute ADD CONSTRAINT FK_ATTR_GROUP FOREIGN KEY (group_id) REFERENCES shop_product_attribute_group (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE shop_product_attribute_group_item DROP FOREIGN KEY FK_ATTR_GROUP_ITEM_GROUP');
        $this->addSql('ALTER TABLE shop_product_attribute_group_item DROP FOREIGN KEY FK_ATTR_GROUP_ITEM_ATTR');
        $this->addSql('DROP TABLE shop_product_attribute_group_item');
    }
}
