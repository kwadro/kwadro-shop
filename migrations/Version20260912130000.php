<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add COD commission settings to site';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('site')) {
            return;
        }

        $table = $schema->getTable('site');

        if (!$table->hasColumn('cod_standard_percent')) {
            $this->addSql('ALTER TABLE site ADD cod_standard_percent NUMERIC(5, 2) DEFAULT 2 NOT NULL');
        }

        if (!$table->hasColumn('cod_novapay_percent')) {
            $this->addSql('ALTER TABLE site ADD cod_novapay_percent NUMERIC(5, 2) DEFAULT 1 NOT NULL');
        }

        if (!$table->hasColumn('cod_commission_notice_uk')) {
            $this->addSql('ALTER TABLE site ADD cod_commission_notice_uk LONGTEXT DEFAULT NULL');
        }

        if (!$table->hasColumn('cod_commission_notice_en')) {
            $this->addSql('ALTER TABLE site ADD cod_commission_notice_en LONGTEXT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('site')) {
            return;
        }

        $table = $schema->getTable('site');

        if ($table->hasColumn('cod_commission_notice_en')) {
            $this->addSql('ALTER TABLE site DROP cod_commission_notice_en');
        }

        if ($table->hasColumn('cod_commission_notice_uk')) {
            $this->addSql('ALTER TABLE site DROP cod_commission_notice_uk');
        }

        if ($table->hasColumn('cod_novapay_percent')) {
            $this->addSql('ALTER TABLE site DROP cod_novapay_percent');
        }

        if ($table->hasColumn('cod_standard_percent')) {
            $this->addSql('ALTER TABLE site DROP cod_standard_percent');
        }
    }
}
