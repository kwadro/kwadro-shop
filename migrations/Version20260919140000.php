<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Compact mobile-friendly email order summary and item list sections';
    }

    public function up(Schema $schema): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($schema->hasTable('shop_email_template_section')) {
            foreach (['order_summary', 'order_items', 'footerv2'] as $name) {
                $this->upsertSection($name, $this->loadSectionContent($name . '.html'), $now);
            }
        }

        if ($schema->hasTable('shop_email_template')) {
            $this->addSql(
                'UPDATE shop_email_template SET content = ?, updated_at = ? WHERE name = ?',
                [$this->loadSectionContent('order_created.html'), $now, 'order_created'],
            );
        }
    }

    public function down(Schema $schema): void
    {
    }

    private function upsertSection(string $name, string $content, string $now): void
    {
        $siteIds = $this->connection->fetchFirstColumn('SELECT id FROM site ORDER BY id ASC');
        foreach ($siteIds as $siteId) {
            $exists = $this->connection->fetchOne(
                'SELECT id FROM shop_email_template_section WHERE site_id = ? AND name = ?',
                [$siteId, $name],
            );

            if ($exists !== false) {
                $this->connection->executeStatement(
                    'UPDATE shop_email_template_section SET content = ?, updated_at = ? WHERE id = ?',
                    [$content, $now, $exists],
                );

                continue;
            }

            $this->connection->insert('shop_email_template_section', [
                'site_id' => $siteId,
                'name' => $name,
                'content' => $content,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function loadSectionContent(string $filename): string
    {
        $path = __DIR__ . '/../resources/email-sections/' . $filename;
        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Email section file not found: %s', $path));
        }

        $content = file_get_contents($path);

        return \is_string($content) ? $content : '';
    }
}
