<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AI Content Agent source history, topics, publication queue, and action log';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('ai_content_source')) {
            $this->addSql('CREATE TABLE ai_content_source (
                id INT AUTO_INCREMENT NOT NULL,
                url_hash VARCHAR(64) NOT NULL,
                url VARCHAR(2048) NOT NULL,
                title VARCHAR(255) DEFAULT NULL,
                excerpt LONGTEXT DEFAULT NULL,
                topic_key VARCHAR(64) DEFAULT NULL,
                discovered_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                last_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX uniq_ai_content_source_url_hash (url_hash),
                INDEX idx_ai_content_source_topic (topic_key),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$schema->hasTable('ai_content_topic')) {
            $this->addSql('CREATE TABLE ai_content_topic (
                id INT AUTO_INCREMENT NOT NULL,
                blog_article_id INT DEFAULT NULL,
                topic_key VARCHAR(64) NOT NULL,
                title VARCHAR(255) NOT NULL,
                status VARCHAR(32) NOT NULL,
                source_count INT DEFAULT 0 NOT NULL,
                processed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX uniq_ai_content_topic_key (topic_key),
                INDEX idx_ai_content_topic_status (status),
                INDEX IDX_ai_content_topic_article (blog_article_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE ai_content_topic ADD CONSTRAINT FK_ai_content_topic_article FOREIGN KEY (blog_article_id) REFERENCES shop_blog_article (id) ON DELETE SET NULL');
        }

        if (!$schema->hasTable('ai_content_publication')) {
            $this->addSql('CREATE TABLE ai_content_publication (
                id INT AUTO_INCREMENT NOT NULL,
                topic_id INT NOT NULL,
                blog_article_id INT DEFAULT NULL,
                scheduled_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                status VARCHAR(32) NOT NULL,
                slug VARCHAR(255) DEFAULT NULL,
                attempt_count INT DEFAULT 0 NOT NULL,
                last_error LONGTEXT DEFAULT NULL,
                published_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                notified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX idx_ai_content_publication_due (status, scheduled_at),
                INDEX IDX_ai_content_publication_topic (topic_id),
                INDEX IDX_ai_content_publication_article (blog_article_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE ai_content_publication ADD CONSTRAINT FK_ai_content_publication_topic FOREIGN KEY (topic_id) REFERENCES ai_content_topic (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE ai_content_publication ADD CONSTRAINT FK_ai_content_publication_article FOREIGN KEY (blog_article_id) REFERENCES shop_blog_article (id) ON DELETE SET NULL');
        }

        if (!$schema->hasTable('ai_content_log')) {
            $this->addSql('CREATE TABLE ai_content_log (
                id INT AUTO_INCREMENT NOT NULL,
                level VARCHAR(16) NOT NULL,
                action VARCHAR(64) NOT NULL,
                message LONGTEXT NOT NULL,
                context JSON DEFAULT NULL COMMENT \'(DC2Type:json)\',
                queue_item_id INT DEFAULT NULL,
                created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX idx_ai_content_log_created (created_at),
                INDEX idx_ai_content_log_level (level),
                INDEX idx_ai_content_log_action (action),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('ai_content_publication')) {
            $this->addSql('ALTER TABLE ai_content_publication DROP FOREIGN KEY FK_ai_content_publication_topic');
            $this->addSql('ALTER TABLE ai_content_publication DROP FOREIGN KEY FK_ai_content_publication_article');
            $this->addSql('DROP TABLE ai_content_publication');
        }

        if ($schema->hasTable('ai_content_topic')) {
            $this->addSql('ALTER TABLE ai_content_topic DROP FOREIGN KEY FK_ai_content_topic_article');
            $this->addSql('DROP TABLE ai_content_topic');
        }

        if ($schema->hasTable('ai_content_source')) {
            $this->addSql('DROP TABLE ai_content_source');
        }

        if ($schema->hasTable('ai_content_log')) {
            $this->addSql('DROP TABLE ai_content_log');
        }
    }
}
