<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Editable bot User-Agent markers for RequestList reports';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_bot_user_agent_marker')) {
            $this->addSql('CREATE TABLE shop_bot_user_agent_marker (
                id INT AUTO_INCREMENT NOT NULL,
                marker VARCHAR(128) NOT NULL,
                note VARCHAR(255) DEFAULT NULL,
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX uniq_shop_bot_user_agent_marker (marker),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        $markers = [
            'googlebot',
            'google.com',
            'bingbot',
            'bing.com',
            'bingpreview',
            'yandexbot',
            'yandex.com',
            'duckduckbot',
            'baiduspider',
            'facebookexternalhit',
            'facebot',
            'twitterbot',
            'linkedinbot',
            'slackbot',
            'discordbot',
            'telegrambot',
            'applebot',
            'semrushbot',
            'ahrefsbot',
            'mj12bot',
            'dotbot',
            'petalbot',
            'bytespider',
            'gptbot',
            'claudebot',
            'anthropic',
            'chatgpt',
            'crawler',
            'spider',
            'slurp',
            'bot/',
            'bot;',
            'bot)',
            'crawl',
        ];

        foreach ($markers as $marker) {
            $escaped = addslashes($marker);
            $this->addSql(sprintf(
                "INSERT IGNORE INTO shop_bot_user_agent_marker (marker, note, is_active, created_at, updated_at)
                 VALUES ('%s', NULL, 1, NOW(), NOW())",
                $escaped,
            ));
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('shop_bot_user_agent_marker')) {
            $this->addSql('DROP TABLE shop_bot_user_agent_marker');
        }
    }
}
