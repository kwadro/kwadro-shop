<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product package dimensions and Nova Poshta waybill fields on orders';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('shop_product')) {
            $table = $schema->getTable('shop_product');

            if (!$table->hasColumn('package_height')) {
                $this->addSql('ALTER TABLE shop_product ADD package_height NUMERIC(8, 2) DEFAULT \'10.00\' NOT NULL');
            }

            if (!$table->hasColumn('package_width')) {
                $this->addSql('ALTER TABLE shop_product ADD package_width NUMERIC(8, 2) DEFAULT \'10.00\' NOT NULL');
            }

            if (!$table->hasColumn('package_length')) {
                $this->addSql('ALTER TABLE shop_product ADD package_length NUMERIC(8, 2) DEFAULT \'10.00\' NOT NULL');
            }
        }

        if ($schema->hasTable('shop_order')) {
            $table = $schema->getTable('shop_order');

            if (!$table->hasColumn('np_waybill_ref')) {
                $this->addSql('ALTER TABLE shop_order ADD np_waybill_ref VARCHAR(64) DEFAULT NULL');
            }

            if (!$table->hasColumn('np_waybill_number')) {
                $this->addSql('ALTER TABLE shop_order ADD np_waybill_number VARCHAR(64) DEFAULT NULL');
            }

            if (!$table->hasColumn('np_waybill_data')) {
                $this->addSql('ALTER TABLE shop_order ADD np_waybill_data JSON DEFAULT NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_product')) {
            $table = $schema->getTable('shop_product');

            if ($table->hasColumn('package_length')) {
                $this->addSql('ALTER TABLE shop_product DROP package_length');
            }

            if ($table->hasColumn('package_width')) {
                $this->addSql('ALTER TABLE shop_product DROP package_width');
            }

            if ($table->hasColumn('package_height')) {
                $this->addSql('ALTER TABLE shop_product DROP package_height');
            }
        }

        if ($schema->hasTable('shop_order')) {
            $table = $schema->getTable('shop_order');

            if ($table->hasColumn('np_waybill_data')) {
                $this->addSql('ALTER TABLE shop_order DROP np_waybill_data');
            }

            if ($table->hasColumn('np_waybill_number')) {
                $this->addSql('ALTER TABLE shop_order DROP np_waybill_number');
            }

            if ($table->hasColumn('np_waybill_ref')) {
                $this->addSql('ALTER TABLE shop_order DROP np_waybill_ref');
            }
        }
    }
}
