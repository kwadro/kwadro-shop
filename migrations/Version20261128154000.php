<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128154000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Homepage contact CTA block fields';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_homepage_setting');

        $columns = [
            'contact_cta_enabled' => 'ALTER TABLE shop_homepage_setting ADD contact_cta_enabled TINYINT(1) DEFAULT 1 NOT NULL',
            'contact_cta_eyebrow' => 'ALTER TABLE shop_homepage_setting ADD contact_cta_eyebrow VARCHAR(255) DEFAULT NULL',
            'contact_cta_title' => 'ALTER TABLE shop_homepage_setting ADD contact_cta_title VARCHAR(255) DEFAULT NULL',
            'contact_cta_lead' => 'ALTER TABLE shop_homepage_setting ADD contact_cta_lead LONGTEXT DEFAULT NULL',
            'contact_cta_badge1' => 'ALTER TABLE shop_homepage_setting ADD contact_cta_badge1 VARCHAR(255) DEFAULT NULL',
            'contact_cta_badge2' => 'ALTER TABLE shop_homepage_setting ADD contact_cta_badge2 VARCHAR(255) DEFAULT NULL',
            'contact_cta_badge3' => 'ALTER TABLE shop_homepage_setting ADD contact_cta_badge3 VARCHAR(255) DEFAULT NULL',
            'contact_cta_phone_label' => 'ALTER TABLE shop_homepage_setting ADD contact_cta_phone_label VARCHAR(255) DEFAULT NULL',
            'contact_cta_phone' => 'ALTER TABLE shop_homepage_setting ADD contact_cta_phone VARCHAR(255) DEFAULT NULL',
            'contact_cta_email' => 'ALTER TABLE shop_homepage_setting ADD contact_cta_email VARCHAR(255) DEFAULT NULL',
            'contact_cta_link1_label' => 'ALTER TABLE shop_homepage_setting ADD contact_cta_link1_label VARCHAR(255) DEFAULT NULL',
            'contact_cta_link1_url' => 'ALTER TABLE shop_homepage_setting ADD contact_cta_link1_url VARCHAR(500) DEFAULT NULL',
            'contact_cta_link2_label' => 'ALTER TABLE shop_homepage_setting ADD contact_cta_link2_label VARCHAR(255) DEFAULT NULL',
            'contact_cta_link2_url' => 'ALTER TABLE shop_homepage_setting ADD contact_cta_link2_url VARCHAR(500) DEFAULT NULL',
            'contact_cta_link3_label' => 'ALTER TABLE shop_homepage_setting ADD contact_cta_link3_label VARCHAR(255) DEFAULT NULL',
            'contact_cta_link3_url' => 'ALTER TABLE shop_homepage_setting ADD contact_cta_link3_url VARCHAR(500) DEFAULT NULL',
            'contact_cta_note' => 'ALTER TABLE shop_homepage_setting ADD contact_cta_note LONGTEXT DEFAULT NULL',
        ];

        foreach ($columns as $name => $sql) {
            if (!$table->hasColumn($name)) {
                $this->addSql($sql);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_homepage_setting');

        foreach ([
            'contact_cta_enabled',
            'contact_cta_eyebrow',
            'contact_cta_title',
            'contact_cta_lead',
            'contact_cta_badge1',
            'contact_cta_badge2',
            'contact_cta_badge3',
            'contact_cta_phone_label',
            'contact_cta_phone',
            'contact_cta_email',
            'contact_cta_link1_label',
            'contact_cta_link1_url',
            'contact_cta_link2_label',
            'contact_cta_link2_url',
            'contact_cta_link3_label',
            'contact_cta_link3_url',
            'contact_cta_note',
        ] as $name) {
            if ($table->hasColumn($name)) {
                $this->addSql(sprintf('ALTER TABLE shop_homepage_setting DROP %s', $name));
            }
        }
    }
}
