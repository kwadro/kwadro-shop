<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Nova Poshta sender city to suppliers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_supplier ADD np_sender_city_name VARCHAR(255) DEFAULT NULL, ADD np_sender_city_ref VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_supplier DROP np_sender_city_name, DROP np_sender_city_ref');
    }
}
