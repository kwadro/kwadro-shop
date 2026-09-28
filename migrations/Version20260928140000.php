<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Facebook and Instagram page URLs to site settings';
    }

    public function up(Schema $schema): void
    {
        $site = $schema->getTable('site');
        if (!$site->hasColumn('facebook_url')) {
            $this->addSql('ALTER TABLE site ADD facebook_url VARCHAR(512) DEFAULT NULL');
        }
        if (!$site->hasColumn('instagram_url')) {
            $this->addSql('ALTER TABLE site ADD instagram_url VARCHAR(512) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $site = $schema->getTable('site');
        if ($site->hasColumn('instagram_url')) {
            $this->addSql('ALTER TABLE site DROP instagram_url');
        }
        if ($site->hasColumn('facebook_url')) {
            $this->addSql('ALTER TABLE site DROP facebook_url');
        }
    }
}
