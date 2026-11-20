<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120202951 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Meta Pixel Enabled to site settings';
    }

    public function up(Schema $schema): void
    {
        $site = $schema->getTable('site');
        if (!$site->hasColumn('meta_pixel_enabled')) {
            $this->addSql('ALTER TABLE site ADD meta_pixel_enabled TINYINT(1) DEFAULT 0 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $site = $schema->getTable('site');
        if ($site->hasColumn('meta_pixel_enabled')) {
            $this->addSql('ALTER TABLE site DROP meta_pixel_enabled');
        }

    }
}
