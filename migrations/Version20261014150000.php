<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product social_posts JSON for Marketing Facebook post variants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE shop_product ADD social_posts JSON DEFAULT ('[]') NOT NULL COMMENT '(DC2Type:json)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_product DROP social_posts');
    }
}
