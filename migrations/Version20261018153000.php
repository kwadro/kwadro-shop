<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add locale to shop mega menu settings (unique per site + locale)';
    }

    public function up(Schema $schema): void
    {
        // Keep an index for FK_SMMS_SITE before dropping the unique site-only index.
        $this->addSql('CREATE INDEX IDX_SMMS_SITE ON shop_mega_menu_setting (site_id)');
        $this->addSql('ALTER TABLE shop_mega_menu_setting DROP INDEX uniq_shop_mega_menu_setting_site');

        $this->addSql('ALTER TABLE shop_mega_menu_setting ADD locale_id INT DEFAULT NULL');
        $this->addSql('UPDATE shop_mega_menu_setting SET locale_id = (
            SELECT id FROM (SELECT id FROM locale ORDER BY id ASC LIMIT 1) AS locale_pick
        ) WHERE locale_id IS NULL');
        $this->addSql('ALTER TABLE shop_mega_menu_setting MODIFY locale_id INT NOT NULL');

        $this->addSql('ALTER TABLE shop_mega_menu_setting ADD CONSTRAINT FK_SMMS_LOCALE FOREIGN KEY (locale_id) REFERENCES locale (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX uniq_shop_mega_menu_setting_site_locale ON shop_mega_menu_setting (site_id, locale_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_mega_menu_setting DROP FOREIGN KEY FK_SMMS_LOCALE');
        $this->addSql('DROP INDEX uniq_shop_mega_menu_setting_site_locale ON shop_mega_menu_setting');
        $this->addSql('ALTER TABLE shop_mega_menu_setting DROP locale_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_shop_mega_menu_setting_site ON shop_mega_menu_setting (site_id)');
        $this->addSql('DROP INDEX IDX_SMMS_SITE ON shop_mega_menu_setting');
    }
}
