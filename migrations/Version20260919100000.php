<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pay_amount column to shop_order and backfill from successful payments';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_order')) {
            return;
        }

        $table = $schema->getTable('shop_order');
        if (!$table->hasColumn('pay_amount')) {
            $this->addSql("ALTER TABLE shop_order ADD pay_amount NUMERIC(12, 2) DEFAULT '0.00' NOT NULL");
        }

        if (!$schema->hasTable('shop_payment')) {
            return;
        }

        $this->addSql(<<<'SQL'
            UPDATE shop_order o
            SET pay_amount = (
                SELECT COALESCE(SUM(p.amount), 0)
                FROM shop_payment p
                WHERE p.order_id = o.id
                  AND p.status = 'success'
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('shop_order')) {
            return;
        }

        $table = $schema->getTable('shop_order');
        if ($table->hasColumn('pay_amount')) {
            $this->addSql('ALTER TABLE shop_order DROP pay_amount');
        }
    }
}
