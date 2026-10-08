<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add category.show_out_of_stock flag for product listing';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_category');
        if (!$table->hasColumn('show_out_of_stock')) {
            $this->addSql('ALTER TABLE shop_category ADD show_out_of_stock TINYINT(1) DEFAULT 1 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_category');
        if ($table->hasColumn('show_out_of_stock')) {
            $this->addSql('ALTER TABLE shop_category DROP show_out_of_stock');
        }
    }
}
