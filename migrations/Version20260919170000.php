<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create bank accounts entity and migrate IBAN email parameters';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_bank_account')) {
            $this->addSql('CREATE TABLE shop_bank_account (id INT AUTO_INCREMENT NOT NULL, site_id INT NOT NULL, locale_id INT NOT NULL, title VARCHAR(255) NOT NULL, iban VARCHAR(34) NOT NULL, bank_name VARCHAR(255) NOT NULL, recipient VARCHAR(255) NOT NULL, edrpou VARCHAR(35) NOT NULL, is_default TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_BANK_ACCOUNT_SITE (site_id), INDEX IDX_BANK_ACCOUNT_LOCALE (locale_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE shop_bank_account ADD CONSTRAINT FK_BANK_ACCOUNT_SITE FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE shop_bank_account ADD CONSTRAINT FK_BANK_ACCOUNT_LOCALE FOREIGN KEY (locale_id) REFERENCES locale (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_bank_account')) {
            $this->addSql('DROP TABLE shop_bank_account');
        }
    }
}
