<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Homepage categories per-row default 4 (match live storefront grid)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_homepage_setting ALTER categories_per_row SET DEFAULT 4');
        $this->addSql('UPDATE shop_homepage_setting SET categories_per_row = 4 WHERE categories_per_row = 3');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_homepage_setting ALTER categories_per_row SET DEFAULT 3');
        $this->addSql('UPDATE shop_homepage_setting SET categories_per_row = 3 WHERE categories_per_row = 4');
    }
}
