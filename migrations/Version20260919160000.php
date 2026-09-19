<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add section column to email parameters and move bank params to bank section';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_parameter')) {
            return;
        }

        $table = $schema->getTable('shop_email_parameter');
        if (!$table->hasColumn('section')) {
            $this->addSql("ALTER TABLE shop_email_parameter ADD section VARCHAR(32) NOT NULL DEFAULT 'general'");
        }

        $this->addSql("UPDATE shop_email_parameter SET section = 'bank' WHERE name IN ('shop_iban', 'shop_bank_name', 'shop_bank_recipient', 'shop_edrpou')");
        $this->addSql("UPDATE shop_email_parameter SET section = 'general' WHERE section = '' OR section IS NULL");
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_parameter')) {
            return;
        }

        $table = $schema->getTable('shop_email_parameter');
        if ($table->hasColumn('section')) {
            $this->addSql('ALTER TABLE shop_email_parameter DROP section');
        }
    }
}
