<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add site relation to email templates';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_template')) {
            return;
        }

        $table = $schema->getTable('shop_email_template');

        if (!$table->hasColumn('site_id')) {
            $this->addSql('ALTER TABLE shop_email_template ADD site_id INT DEFAULT NULL');
        }

        $this->addSql('UPDATE shop_email_template et SET site_id = (SELECT s.id FROM site s ORDER BY s.id ASC LIMIT 1) WHERE et.site_id IS NULL');

        foreach ($table->getIndexes() as $index) {
            if (!$index->isUnique()) {
                continue;
            }

            if ($index->getColumns() === ['name']) {
                $this->addSql(sprintf('DROP INDEX %s ON shop_email_template', $index->getName()));
            }
        }

        if (!$table->hasIndex('uniq_email_template_site_name')) {
            $this->addSql('CREATE UNIQUE INDEX uniq_email_template_site_name ON shop_email_template (site_id, name)');
        }

        if (!$table->hasForeignKey('FK_EMAIL_TEMPLATE_SITE')) {
            $this->addSql('ALTER TABLE shop_email_template ADD CONSTRAINT FK_EMAIL_TEMPLATE_SITE FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE');
        }

        $this->addSql('ALTER TABLE shop_email_template MODIFY site_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_template')) {
            return;
        }

        $this->addSql('ALTER TABLE shop_email_template DROP FOREIGN KEY FK_EMAIL_TEMPLATE_SITE');
        $this->addSql('DROP INDEX uniq_email_template_site_name ON shop_email_template');
        $this->addSql('ALTER TABLE shop_email_template DROP site_id');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EMAIL_TEMPLATE_NAME ON shop_email_template (name)');
    }
}
