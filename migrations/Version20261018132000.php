<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018132000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Homepage hero slide image object-position (horizontal %)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_homepage_hero_slide ADD image_position INT DEFAULT 70 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_homepage_hero_slide DROP image_position');
    }
}
