<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add contact_data column to shop_cart for separate checkout contact step';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_cart ADD contact_data JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_cart DROP contact_data');
    }
}
