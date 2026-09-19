<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add active payment and delivery method settings to site';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('site')) {
            return;
        }

        $table = $schema->getTable('site');

        if (!$table->hasColumn('active_payment_methods')) {
            $this->addSql('ALTER TABLE site ADD active_payment_methods JSON DEFAULT NULL');
        }

        if (!$table->hasColumn('active_delivery_methods')) {
            $this->addSql('ALTER TABLE site ADD active_delivery_methods JSON DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('site')) {
            return;
        }

        $table = $schema->getTable('site');

        if ($table->hasColumn('active_delivery_methods')) {
            $this->addSql('ALTER TABLE site DROP active_delivery_methods');
        }

        if ($table->hasColumn('active_payment_methods')) {
            $this->addSql('ALTER TABLE site DROP active_payment_methods');
        }
    }
}
