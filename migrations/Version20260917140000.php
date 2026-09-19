<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed default email parameters when table is empty';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_parameter')) {
            return;
        }

        $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM shop_email_parameter');
        if ($count > 0) {
            return;
        }

        $sites = $this->connection->fetchAllAssociative('SELECT id, domain FROM site ORDER BY id ASC');
        $locales = $this->connection->fetchAllAssociative('SELECT id, code FROM locale ORDER BY id ASC');

        if ($sites === [] || $locales === []) {
            return;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($sites as $site) {
            $siteId = (int) $site['id'];
            $domain = trim((string) ($site['domain'] ?? ''));
            $shopUrl = $domain !== '' ? sprintf('https://%s', $domain) : 'https://kwadro.com.ua';
            $shopLogoUrl = sprintf('%s/uploads/images/favicon.png', rtrim($shopUrl, '/'));

            foreach ($locales as $locale) {
                $localeId = (int) $locale['id'];
                $localeCode = trim((string) ($locale['code'] ?? 'uk'));
                $shopTitle = $this->resolveShopTitle($siteId, $localeId);

                foreach ([
                    'shop_logo_url' => $shopLogoUrl,
                    'shop_title' => $shopTitle,
                    'shop_url' => $shopUrl,
                    'support_phone' => '066-913-30-97',
                    'support_email' => 'info@kwadro.com.ua',
                ] as $name => $value) {
                    $this->connection->insert('shop_email_parameter', [
                        'site_id' => $siteId,
                        'locale_id' => $localeId,
                        'name' => $name,
                        'value' => $value,
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

        $this->addSql("DELETE FROM shop_email_parameter WHERE name IN ('shop_logo_url', 'shop_title', 'shop_url', 'support_phone', 'support_email')");
    }

    private function resolveShopTitle(int $siteId, int $localeId): string
    {
        $title = $this->connection->fetchOne(
            'SELECT ht.title FROM header_translation ht
             INNER JOIN header_setting hs ON hs.id = ht.headersetting_id
             WHERE hs.site_id = ? AND ht.locale_id = ?
             ORDER BY ht.id ASC LIMIT 1',
            [$siteId, $localeId],
        );

        if (\is_string($title) && trim($title) !== '') {
            return trim($title);
        }

        return 'Kvadro';
    }
}
