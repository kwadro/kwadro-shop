<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mark attribute group memberships as deleted to skip them on product import';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_product_attribute_group_item ADD deleted TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_product_attribute_group_item DROP deleted');
    }
}
