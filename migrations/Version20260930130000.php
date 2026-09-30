<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ignored_request_ips to site for RequestList filtering';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('site');
        if (!$table->hasColumn('ignored_request_ips')) {
            $this->addSql('ALTER TABLE site ADD ignored_request_ips LONGTEXT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('site');
        if ($table->hasColumn('ignored_request_ips')) {
            $this->addSql('ALTER TABLE site DROP ignored_request_ips');
        }
    }
}
