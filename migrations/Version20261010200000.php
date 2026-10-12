<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shop_category.external_id for supplier/feed category mapping';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_category');
        if (!$table->hasColumn('external_id')) {
            $this->addSql('ALTER TABLE shop_category ADD external_id INT DEFAULT NULL');
            $this->addSql('CREATE INDEX IDX_SHOP_CATEGORY_EXTERNAL_ID ON shop_category (external_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_category');
        if ($table->hasColumn('external_id')) {
            $this->addSql('DROP INDEX IDX_SHOP_CATEGORY_EXTERNAL_ID ON shop_category');
            $this->addSql('ALTER TABLE shop_category DROP external_id');
        }
    }
}
