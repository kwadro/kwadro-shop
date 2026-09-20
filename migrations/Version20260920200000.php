<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260920200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add monobank_invoice_id to shop_payment';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_payment');
        if (!$table->hasColumn('monobank_invoice_id')) {
            $this->addSql('ALTER TABLE shop_payment ADD monobank_invoice_id VARCHAR(64) DEFAULT NULL');
            $this->addSql('CREATE INDEX IDX_shop_payment_monobank_invoice ON shop_payment (monobank_invoice_id)');
        }

        if ($this->connection->getDatabasePlatform()->getName() === 'mysql') {
            $this->addSql(<<<'SQL'
                UPDATE shop_payment
                SET monobank_invoice_id = JSON_UNQUOTE(JSON_EXTRACT(gateway_response, '$.invoiceId'))
                WHERE method = 'monobank'
                  AND monobank_invoice_id IS NULL
                  AND gateway_response IS NOT NULL
                  AND JSON_EXTRACT(gateway_response, '$.invoiceId') IS NOT NULL
                SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_payment');
        if ($table->hasColumn('monobank_invoice_id')) {
            $this->addSql('DROP INDEX IDX_shop_payment_monobank_invoice ON shop_payment');
            $this->addSql('ALTER TABLE shop_payment DROP monobank_invoice_id');
        }
    }
}
