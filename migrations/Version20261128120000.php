<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Partner referral hits table (FB / Google Ads click stats)';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('shop_partner_referral_hit')) {
            return;
        }

        $this->addSql('CREATE TABLE shop_partner_referral_hit (
            id INT AUTO_INCREMENT NOT NULL,
            referral_key VARCHAR(64) NOT NULL,
            param VARCHAR(64) DEFAULT NULL,
            landing_url LONGTEXT NOT NULL,
            visitor_id VARCHAR(64) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            ad_source VARCHAR(32) DEFAULT NULL,
            fbclid VARCHAR(512) DEFAULT NULL,
            fbc VARCHAR(512) DEFAULT NULL,
            fbp VARCHAR(512) DEFAULT NULL,
            gclid VARCHAR(512) DEFAULT NULL,
            gbraid VARCHAR(512) DEFAULT NULL,
            wbraid VARCHAR(512) DEFAULT NULL,
            utm_source VARCHAR(255) DEFAULT NULL,
            utm_medium VARCHAR(255) DEFAULT NULL,
            utm_campaign VARCHAR(255) DEFAULT NULL,
            advertising_link_id INT DEFAULT NULL,
            created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_shop_partner_referral_hit_created (created_at),
            INDEX idx_shop_partner_referral_hit_ad_source (ad_source),
            INDEX idx_shop_partner_referral_hit_referral_key (referral_key),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_partner_referral_hit')) {
            $this->addSql('DROP TABLE shop_partner_referral_hit');
        }
    }
}
