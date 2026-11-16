<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product rating for catalog sorting (default 1)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_product ADD rating INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_product DROP rating');
    }
}
