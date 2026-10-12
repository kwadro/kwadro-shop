<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Limit homepage "New offers" block to 5 products';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_homepage_setting CHANGE products_limit products_limit INT DEFAULT 5 NOT NULL');
        $this->addSql('UPDATE shop_homepage_setting SET products_limit = 5 WHERE products_limit > 5');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_homepage_setting CHANGE products_limit products_limit INT DEFAULT 8 NOT NULL');
    }
}
