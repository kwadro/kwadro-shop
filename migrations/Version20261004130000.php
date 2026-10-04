<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_agent and path_after_redirect to shop_request_list';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_request_list');
        if (!$table->hasColumn('user_agent')) {
            $this->addSql('ALTER TABLE shop_request_list ADD user_agent VARCHAR(512) DEFAULT NULL');
        }
        if (!$table->hasColumn('path_after_redirect')) {
            $this->addSql('ALTER TABLE shop_request_list ADD path_after_redirect VARCHAR(2048) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_request_list');
        if ($table->hasColumn('path_after_redirect')) {
            $this->addSql('ALTER TABLE shop_request_list DROP path_after_redirect');
        }
        if ($table->hasColumn('user_agent')) {
            $this->addSql('ALTER TABLE shop_request_list DROP user_agent');
        }
    }
}
