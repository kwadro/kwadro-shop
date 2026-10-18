<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Homepage hero block: enable flag + multi-section slides';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        // Safe re-run after a failed CREATE (hero_enabled may already exist).
        if (!$sm->introspectTable('shop_homepage_setting')->hasColumn('hero_enabled')) {
            $this->addSql('ALTER TABLE shop_homepage_setting ADD hero_enabled TINYINT(1) DEFAULT 1 NOT NULL');
        }

        if (!$sm->tablesExist(['shop_homepage_hero_slide'])) {
            // Column is lead_text — "lead" is reserved in MySQL.
            $this->addSql('CREATE TABLE shop_homepage_hero_slide (
                id INT AUTO_INCREMENT NOT NULL,
                homepage_setting_id INT NOT NULL,
                enabled TINYINT(1) DEFAULT 1 NOT NULL,
                position INT DEFAULT 0 NOT NULL,
                brand VARCHAR(255) DEFAULT NULL,
                title VARCHAR(255) DEFAULT NULL,
                lead_text LONGTEXT DEFAULT NULL,
                image VARCHAR(255) DEFAULT NULL,
                primary_cta_label VARCHAR(255) DEFAULT NULL,
                primary_cta_url VARCHAR(1024) DEFAULT NULL,
                secondary_cta_label VARCHAR(255) DEFAULT NULL,
                secondary_cta_url VARCHAR(1024) DEFAULT NULL,
                INDEX IDX_HHS_SETTING (homepage_setting_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE shop_homepage_hero_slide ADD CONSTRAINT FK_HHS_SETTING FOREIGN KEY (homepage_setting_id) REFERENCES shop_homepage_setting (id) ON DELETE CASCADE');
        } else {
            // Dev DBs that already created a reserved `lead` column.
            $hero = $sm->introspectTable('shop_homepage_hero_slide');
            if ($hero->hasColumn('lead') && !$hero->hasColumn('lead_text')) {
                $this->addSql('ALTER TABLE shop_homepage_hero_slide CHANGE `lead` lead_text LONGTEXT DEFAULT NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['shop_homepage_hero_slide'])) {
            $this->addSql('ALTER TABLE shop_homepage_hero_slide DROP FOREIGN KEY FK_HHS_SETTING');
            $this->addSql('DROP TABLE shop_homepage_hero_slide');
        }

        if ($sm->introspectTable('shop_homepage_setting')->hasColumn('hero_enabled')) {
            $this->addSql('ALTER TABLE shop_homepage_setting DROP hero_enabled');
        }
    }
}
