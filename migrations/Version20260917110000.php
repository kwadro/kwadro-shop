<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add context field to email templates (order or user)';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_template')) {
            return;
        }

        $table = $schema->getTable('shop_email_template');
        if (!$table->hasColumn('context')) {
            $this->addSql("ALTER TABLE shop_email_template ADD context VARCHAR(16) NOT NULL DEFAULT 'order'");
        }

        if ($schema->hasTable('shop_order_email')) {
            $this->addSql(<<<'SQL'
                UPDATE shop_email_template t
                INNER JOIN shop_order_email oe ON oe.template_id = t.id
                SET t.context = 'user'
                WHERE oe.code = 'register_user'
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_template')) {
            return;
        }

        $table = $schema->getTable('shop_email_template');
        if ($table->hasColumn('context')) {
            $this->addSql('ALTER TABLE shop_email_template DROP context');
        }
    }
}
