<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114103500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Speed up mega menu lookup by site/locale/status/position';
    }

    public function up(Schema $schema): void
    {
        // Covers join + filters + ORDER BY; leftmost prefix also replaces the plain megamenusetting_id FK index.
        $this->addSql('CREATE INDEX idx_mmt_setting_locale_status_pos ON mega_menu_translation (megamenusetting_id, locale_id, status, position)');
        $this->addSql('DROP INDEX IDX_1BE2B31FA01F9A11 ON mega_menu_translation');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_1BE2B31FA01F9A11 ON mega_menu_translation (megamenusetting_id)');
        $this->addSql('DROP INDEX idx_mmt_setting_locale_status_pos ON mega_menu_translation');
    }
}
