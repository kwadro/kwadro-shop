<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed IBAN email parameters for shipment prepayment page';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_parameter')) {
            return;
        }

        $sites = $this->connection->fetchAllAssociative('SELECT id FROM site ORDER BY id ASC');
        $locales = $this->connection->fetchAllAssociative('SELECT id, code FROM locale ORDER BY id ASC');
        if ($sites === [] || $locales === []) {
            return;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $defaults = [
            'shop_iban' => '',
            'shop_bank_name' => '',
            'shop_bank_recipient' => '',
            'shop_edrpou' => '',
        ];

        foreach ($sites as $site) {
            $siteId = (int) $site['id'];
            foreach ($locales as $locale) {
                $localeId = (int) $locale['id'];
                $localeCode = trim((string) ($locale['code'] ?? 'uk'));

                foreach ($defaults as $name => $defaultValue) {
                    $exists = $this->connection->fetchOne(
                        'SELECT id FROM shop_email_parameter WHERE site_id = ? AND locale_id = ? AND name = ?',
                        [$siteId, $localeId, $name],
                    );

                    if ($exists !== false) {
                        continue;
                    }

                    $value = $defaultValue;
                    if ($name === 'shop_bank_recipient' && $localeCode === 'uk') {
                        $value = 'ТОВ «Квадро»';
                    }
                    if ($name === 'shop_bank_recipient' && $localeCode === 'en') {
                        $value = 'Kvadro LLC';
                    }

                    $this->connection->insert('shop_email_parameter', [
                        'site_id' => $siteId,
                        'locale_id' => $localeId,
                        'name' => $name,
                        'value' => $value,
                        'type' => 'text',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_parameter')) {
            return;
        }

        $this->addSql("DELETE FROM shop_email_parameter WHERE name IN ('shop_iban', 'shop_bank_name', 'shop_bank_recipient', 'shop_edrpou')");
    }
}
