<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Per-group show_in_filter override on attribute group memberships';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_product_attribute_group_item ADD show_in_filter TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_product_attribute_group_item DROP show_in_filter');
    }
}
