<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed order_items email template section';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_template_section')) {
            return;
        }

        $content = $this->loadSectionContent('order_items.html');
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $siteIds = $this->connection->fetchFirstColumn('SELECT id FROM site ORDER BY id ASC');
        foreach ($siteIds as $siteId) {
            $exists = $this->connection->fetchOne(
                'SELECT id FROM shop_email_template_section WHERE site_id = ? AND name = ?',
                [$siteId, 'order_items'],
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
                'name' => 'order_items',
                'content' => $content,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_template_section')) {
            return;
        }

        $this->addSql("DELETE FROM shop_email_template_section WHERE name = 'order_items'");
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
