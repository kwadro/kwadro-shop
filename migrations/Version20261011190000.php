<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename Lugi menu section to Товари для дому and put it first';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE shop_category SET name = 'Товари для дому', position = 0 WHERE slug = 'homeware'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE shop_category SET name = 'Lugi', position = 3 WHERE slug = 'homeware'");
    }
}
