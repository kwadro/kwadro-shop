<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add image type for email parameters and configure shop_logo_url as image';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_parameter')) {
            return;
        }

        $table = $schema->getTable('shop_email_parameter');
        if (!$table->hasColumn('type')) {
            $this->addSql("ALTER TABLE shop_email_parameter ADD type VARCHAR(16) NOT NULL DEFAULT 'text'");
        }

        $this->addSql("UPDATE shop_email_parameter SET type = 'image' WHERE name = 'shop_logo_url'");

        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, value FROM shop_email_parameter WHERE name = 'shop_logo_url'",
        );

        foreach ($rows as $row) {
            $value = trim((string) ($row['value'] ?? ''));
            if ($value === '' || !str_contains($value, '/')) {
                continue;
            }

            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                $filename = basename(parse_url($value, PHP_URL_PATH) ?: $value);
                if ($filename !== '') {
                    $this->connection->update('shop_email_parameter', ['value' => $filename], ['id' => $row['id']]);
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_parameter')) {
            return;
        }

        $table = $schema->getTable('shop_email_parameter');
        if ($table->hasColumn('type')) {
            $this->addSql('ALTER TABLE shop_email_parameter DROP type');
        }
    }
}
