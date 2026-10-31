<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Meta Pixel ID to site settings';
    }

    public function up(Schema $schema): void
    {
        $site = $schema->getTable('site');
        if (!$site->hasColumn('meta_pixel_id')) {
            $this->addSql('ALTER TABLE site ADD meta_pixel_id VARCHAR(32) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $site = $schema->getTable('site');
        if ($site->hasColumn('meta_pixel_id')) {
            $this->addSql('ALTER TABLE site DROP meta_pixel_id');
        }
    }
}
