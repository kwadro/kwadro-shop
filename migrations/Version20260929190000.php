<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add facebook_draft plain-text field to blog articles';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_blog_article');
        if (!$table->hasColumn('facebook_draft')) {
            $this->addSql('ALTER TABLE shop_blog_article ADD facebook_draft LONGTEXT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_blog_article');
        if ($table->hasColumn('facebook_draft')) {
            $this->addSql('ALTER TABLE shop_blog_article DROP facebook_draft');
        }
    }
}
