<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed default email template header and footer sections';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_template_section')) {
            return;
        }

        $siteId = $this->connection->fetchOne('SELECT id FROM site ORDER BY id ASC LIMIT 1');
        if ($siteId === false) {
            return;
        }

        $sections = [
            'header' => $this->loadSectionContent('header.html'),
            'footer' => $this->loadSectionContent('footer.html'),
        ];

        foreach ($sections as $name => $content) {
            $exists = $this->connection->fetchOne(
                'SELECT id FROM shop_email_template_section WHERE site_id = ? AND name = ?',
                [$siteId, $name],
            );

            if ($exists !== false) {
                continue;
            }

            $this->connection->insert('shop_email_template_section', [
                'site_id' => $siteId,
                'name' => $name,
                'content' => $content,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_template_section')) {
            return;
        }

        $this->addSql("DELETE FROM shop_email_template_section WHERE name IN ('header', 'footer')");
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
