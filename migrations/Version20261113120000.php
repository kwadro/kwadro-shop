<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename shop locale code uk → ua for URL prefixes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE locale SET code = 'ua' WHERE code = 'uk'");
        $this->addSql("UPDATE shop_product_feed_setting SET locale_code = 'ua' WHERE locale_code = 'uk'");
        $this->addSql("ALTER TABLE shop_product_feed_setting CHANGE locale_code locale_code VARCHAR(8) DEFAULT 'ua' NOT NULL");
        $this->addSql("UPDATE shop_order SET locale = 'ua' WHERE locale = 'uk'");
        $this->addSql("UPDATE shop_redirect SET to_path = REPLACE(to_path, '/uk/', '/ua/') WHERE to_path LIKE '%/uk/%'");
        $this->addSql("UPDATE shop_redirect SET from_path = REPLACE(from_path, '/uk/', '/ua/') WHERE from_path LIKE '%/uk/%'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE locale SET code = 'uk' WHERE code = 'ua'");
        $this->addSql("UPDATE shop_product_feed_setting SET locale_code = 'uk' WHERE locale_code = 'ua'");
        $this->addSql("ALTER TABLE shop_product_feed_setting CHANGE locale_code locale_code VARCHAR(8) DEFAULT 'uk' NOT NULL");
        $this->addSql("UPDATE shop_order SET locale = 'uk' WHERE locale = 'ua'");
        $this->addSql("UPDATE shop_redirect SET to_path = REPLACE(to_path, '/ua/', '/uk/') WHERE to_path LIKE '%/ua/%'");
        $this->addSql("UPDATE shop_redirect SET from_path = REPLACE(from_path, '/ua/', '/uk/') WHERE from_path LIKE '%/ua/%'");
    }
}
