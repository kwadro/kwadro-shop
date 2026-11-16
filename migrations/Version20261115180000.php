<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Partner program: allowed referral query parameter names';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_partner_query_parameter (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(64) NOT NULL, note VARCHAR(255) DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_shop_partner_query_parameter_name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql("INSERT INTO shop_partner_query_parameter (name, note, is_active, created_at, updated_at) VALUES ('pr', 'Default partner referral param', 1, NOW(), NOW()), ('ref', 'Alternate referral param', 1, NOW(), NOW()), ('key', 'Generic key param', 1, NOW(), NOW()), ('bb_ref', 'BringBuyer cookie/query alias', 1, NOW(), NOW())");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shop_partner_query_parameter');
    }
}
