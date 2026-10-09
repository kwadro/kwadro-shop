<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shop_product.clean_image for background-removed product photo';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_product');
        if (!$table->hasColumn('clean_image')) {
            $this->addSql('ALTER TABLE shop_product ADD clean_image VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_product');
        if ($table->hasColumn('clean_image')) {
            $this->addSql('ALTER TABLE shop_product DROP clean_image');
        }
    }
}
