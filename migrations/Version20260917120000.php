<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create email parameters table and seed default branding values per site and locale';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('shop_email_parameter')) {
            return;
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE shop_email_parameter (
                id INT AUTO_INCREMENT NOT NULL,
                site_id INT NOT NULL,
                locale_id INT NOT NULL,
                name VARCHAR(64) NOT NULL,
                value LONGTEXT NOT NULL,
                created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_EMAIL_PARAMETER_SITE (site_id),
                INDEX IDX_EMAIL_PARAMETER_LOCALE (locale_id),
                UNIQUE INDEX uniq_email_parameter_site_locale_name (site_id, locale_id, name),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE shop_email_parameter ADD CONSTRAINT FK_EMAIL_PARAMETER_SITE FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_email_parameter ADD CONSTRAINT FK_EMAIL_PARAMETER_LOCALE FOREIGN KEY (locale_id) REFERENCES locale (id) ON DELETE CASCADE');
    }

    public function postUp(Schema $schema): void
    {
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
                $shopTitle = $this->resolveShopTitle($siteId, $localeId, $localeCode);

                $defaults = [
                    'shop_logo_url' => $shopLogoUrl,
                    'shop_title' => $shopTitle,
                    'shop_url' => $shopUrl,
                    'support_phone' => '066-913-30-97',
                    'support_email' => 'info@kwadro.com.ua',
                ];

                foreach ($defaults as $name => $value) {
                    $exists = $this->connection->fetchOne(
                        'SELECT id FROM shop_email_parameter WHERE site_id = ? AND locale_id = ? AND name = ?',
                        [$siteId, $localeId, $name],
                    );

                    if ($exists !== false) {
                        continue;
                    }

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

        $this->addSql('ALTER TABLE shop_email_parameter DROP FOREIGN KEY FK_EMAIL_PARAMETER_SITE');
        $this->addSql('ALTER TABLE shop_email_parameter DROP FOREIGN KEY FK_EMAIL_PARAMETER_LOCALE');
        $this->addSql('DROP TABLE shop_email_parameter');
    }

    private function resolveShopTitle(int $siteId, int $localeId, string $localeCode): string
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

        return $localeCode === 'en' ? 'Kvadro' : 'Kvadro';
    }
}
