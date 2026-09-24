<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add feature_id (featured product) to site';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('site');

        if (!$table->hasColumn('feature_id')) {
            $this->addSql('ALTER TABLE site ADD feature_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE site ADD CONSTRAINT FK_site_feature_product FOREIGN KEY (feature_id) REFERENCES shop_product (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_site_feature_id ON site (feature_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('site');

        if ($table->hasColumn('feature_id')) {
            $this->addSql('ALTER TABLE site DROP FOREIGN KEY FK_site_feature_product');
            $this->addSql('DROP INDEX IDX_site_feature_id ON site');
            $this->addSql('ALTER TABLE site DROP feature_id');
        }
    }
}
