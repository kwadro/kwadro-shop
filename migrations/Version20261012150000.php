<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add supplier flag controlling whether its products are listed in the sitemap';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_supplier ADD show_products_in_sitemap TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_supplier DROP show_products_in_sitemap');
    }
}
