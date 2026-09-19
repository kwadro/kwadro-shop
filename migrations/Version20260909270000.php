<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909270000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add work_hours field to header_translation';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('header_translation') && !$schema->getTable('header_translation')->hasColumn('work_hours')) {
            $this->addSql('ALTER TABLE header_translation ADD work_hours VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('header_translation') && $schema->getTable('header_translation')->hasColumn('work_hours')) {
            $this->addSql('ALTER TABLE header_translation DROP work_hours');
        }
    }

    public function postUp(Schema $schema): void
    {
        if (!$schema->hasTable('header_translation') || !$schema->getTable('header_translation')->hasColumn('work_hours')) {
            return;
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT ht.id, l.code
             FROM header_translation ht
             LEFT JOIN locale l ON l.id = ht.locale_id
             WHERE ht.work_hours IS NULL OR ht.work_hours = \'\''
        );

        foreach ($rows as $row) {
            $default = match ((string) ($row['code'] ?? '')) {
                'en' => '7 days a week, 9:00–19:00',
                default => '7 днів на тиждень з 9:00 до 19:00',
            };

            $this->connection->update('header_translation', ['work_hours' => $default], ['id' => $row['id']]);
        }
    }
}
