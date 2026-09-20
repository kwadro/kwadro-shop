<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add site relation to shop_order';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_order');
        if (!$table->hasColumn('site_id')) {
            $this->addSql('ALTER TABLE shop_order ADD site_id INT DEFAULT NULL');
            $this->addSql('CREATE INDEX IDX_shop_order_site ON shop_order (site_id)');
            $this->addSql('ALTER TABLE shop_order ADD CONSTRAINT FK_shop_order_site FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_order');
        if ($table->hasColumn('site_id')) {
            $this->addSql('ALTER TABLE shop_order DROP FOREIGN KEY FK_shop_order_site');
            $this->addSql('DROP INDEX IDX_shop_order_site ON shop_order');
            $this->addSql('ALTER TABLE shop_order DROP site_id');
        }
    }
}
