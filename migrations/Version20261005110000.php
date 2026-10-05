<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product model, brand, color, type fields';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_product');
        if (!$table->hasColumn('model')) {
            $this->addSql('ALTER TABLE shop_product ADD model VARCHAR(120) DEFAULT NULL');
        }
        if (!$table->hasColumn('brand')) {
            $this->addSql('ALTER TABLE shop_product ADD brand VARCHAR(120) DEFAULT NULL');
        }
        if (!$table->hasColumn('color')) {
            $this->addSql('ALTER TABLE shop_product ADD color VARCHAR(120) DEFAULT NULL');
        }
        if (!$table->hasColumn('type')) {
            $this->addSql('ALTER TABLE shop_product ADD type VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_product');
        foreach (['type', 'color', 'brand', 'model'] as $column) {
            if ($table->hasColumn($column)) {
                $this->addSql(sprintf('ALTER TABLE shop_product DROP %s', $column));
            }
        }
    }
}
