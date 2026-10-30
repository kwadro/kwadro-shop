<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename reserved MySQL column lead → lead_text on shop_homepage_hero_slide.
 */
final class Version20261019010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename shop_homepage_hero_slide.lead to lead_text (MySQL reserved word)';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['shop_homepage_hero_slide'])) {
            return;
        }

        $table = $sm->introspectTable('shop_homepage_hero_slide');
        if ($table->hasColumn('lead') && !$table->hasColumn('lead_text')) {
            $this->addSql('ALTER TABLE shop_homepage_hero_slide CHANGE `lead` lead_text LONGTEXT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist(['shop_homepage_hero_slide'])) {
            return;
        }

        $table = $sm->introspectTable('shop_homepage_hero_slide');
        if ($table->hasColumn('lead_text') && !$table->hasColumn('lead')) {
            $this->addSql('ALTER TABLE shop_homepage_hero_slide CHANGE lead_text `lead` LONGTEXT DEFAULT NULL');
        }
    }
}
