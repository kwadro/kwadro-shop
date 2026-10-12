<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add minimum order amount to suppliers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_supplier ADD min_order_amount NUMERIC(10, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_supplier DROP min_order_amount');
    }
}
