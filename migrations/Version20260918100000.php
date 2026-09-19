<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace shop_order.contact_data JSON with customer_name, customer_phone, customer_email, do_not_call columns';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_order')) {
            return;
        }

        $table = $schema->getTable('shop_order');

        if (!$table->hasColumn('customer_name')) {
            $this->addSql('ALTER TABLE shop_order ADD customer_name VARCHAR(255) NOT NULL DEFAULT \'\'' );
        }

        if (!$table->hasColumn('customer_phone')) {
            $this->addSql('ALTER TABLE shop_order ADD customer_phone VARCHAR(32) NOT NULL DEFAULT \'\'' );
        }

        if (!$table->hasColumn('customer_email')) {
            $this->addSql('ALTER TABLE shop_order ADD customer_email VARCHAR(255) NOT NULL DEFAULT \'\'' );
        }

        if (!$table->hasColumn('do_not_call')) {
            $this->addSql('ALTER TABLE shop_order ADD do_not_call TINYINT(1) NOT NULL DEFAULT 0');
        }

        if ($table->hasColumn('contact_data')) {
            $this->addSql(<<<'SQL'
                UPDATE shop_order
                SET
                    customer_name = COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(contact_data, '$.customerName')), 'null'), ''),
                    customer_phone = COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(contact_data, '$.customerPhone')), 'null'), ''),
                    customer_email = COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(contact_data, '$.customerEmail')), 'null'), ''),
                    do_not_call = IF(JSON_EXTRACT(contact_data, '$.doNotCall') IN (true, 'true', 1, '1'), 1, 0)
            SQL);
            $this->addSql('ALTER TABLE shop_order DROP contact_data');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('shop_order')) {
            return;
        }

        $table = $schema->getTable('shop_order');

        if (!$table->hasColumn('contact_data')) {
            $this->addSql('ALTER TABLE shop_order ADD contact_data JSON NOT NULL');
        }

        if ($table->hasColumn('customer_name')) {
            $this->addSql(<<<'SQL'
                UPDATE shop_order
                SET contact_data = JSON_OBJECT(
                    'customerName', customer_name,
                    'customerPhone', customer_phone,
                    'customerEmail', customer_email,
                    'doNotCall', IF(do_not_call = 1, true, false)
                )
            SQL);
            $this->addSql('ALTER TABLE shop_order DROP customer_name');
        }

        if ($table->hasColumn('customer_phone')) {
            $this->addSql('ALTER TABLE shop_order DROP customer_phone');
        }

        if ($table->hasColumn('customer_email')) {
            $this->addSql('ALTER TABLE shop_order DROP customer_email');
        }

        if ($table->hasColumn('do_not_call')) {
            $this->addSql('ALTER TABLE shop_order DROP do_not_call');
        }
    }
}
