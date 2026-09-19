<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Redesign order summary and email footers with icon images';
    }

    public function up(Schema $schema): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($schema->hasTable('shop_email_template_section')) {
            $this->upsertSection('order_summary', $this->loadSectionContent('order_summary.html'), $now);
            $this->upsertSection('footerv2', $this->loadSectionContent('footerv2.html'), $now);
            $this->upsertSection('footer', $this->loadSectionContent('footer.html'), $now);
        }

        if ($schema->hasTable('shop_email_template')) {
            $content = $this->loadSectionContent('order_created.html');
            $this->addSql(
                'UPDATE shop_email_template SET content = ?, updated_at = ? WHERE name = ?',
                [$content, $now, 'order_created'],
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
