<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update footer email section to use support_phone and support_email parameters';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_template_section')) {
            return;
        }

        $content = $this->loadSectionContent('footer.html');
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->executeStatement(
            'UPDATE shop_email_template_section SET content = ?, updated_at = ? WHERE name = ?',
            [$content, $now, 'footer'],
        );
    }

    public function down(Schema $schema): void
    {
        // Previous footer content is not restored.
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
