<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260913100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add COD prepayment amount setting to site';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('site')) {
            return;
        }

        $table = $schema->getTable('site');

        if (!$table->hasColumn('cod_prepayment_amount')) {
            $this->addSql('ALTER TABLE site ADD cod_prepayment_amount NUMERIC(12, 2) DEFAULT 100 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('site')) {
            return;
        }

        $table = $schema->getTable('site');

        if ($table->hasColumn('cod_prepayment_amount')) {
            $this->addSql('ALTER TABLE site DROP cod_prepayment_amount');
        }
    }
}
