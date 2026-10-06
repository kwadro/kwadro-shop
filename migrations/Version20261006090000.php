<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shop_product.enabled flag (default true)';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_product');
        if (!$table->hasColumn('enabled')) {
            $this->addSql('ALTER TABLE shop_product ADD enabled TINYINT(1) DEFAULT 1 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_product');
        if ($table->hasColumn('enabled')) {
            $this->addSql('ALTER TABLE shop_product DROP enabled');
        }
    }
}
