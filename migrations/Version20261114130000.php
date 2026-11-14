<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'User roles/groups; assign Client by default and user #1 to Super Administrator';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('app_user_role')) {
            $this->addSql('CREATE TABLE app_user_role (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(64) NOT NULL,
                created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX uniq_app_user_role_code (code),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$schema->hasTable('app_user_group')) {
            $this->addSql('CREATE TABLE app_user_group (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(64) NOT NULL,
                created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                UNIQUE INDEX uniq_app_user_group_code (code),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$schema->hasTable('app_user_group_role')) {
            $this->addSql('CREATE TABLE app_user_group_role (
                user_group_id INT NOT NULL,
                user_role_id INT NOT NULL,
                INDEX IDX_USER_GROUP_ROLE_GROUP (user_group_id),
                INDEX IDX_USER_GROUP_ROLE_ROLE (user_role_id),
                PRIMARY KEY(user_group_id, user_role_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE app_user_group_role ADD CONSTRAINT FK_USER_GROUP_ROLE_GROUP FOREIGN KEY (user_group_id) REFERENCES app_user_group (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE app_user_group_role ADD CONSTRAINT FK_USER_GROUP_ROLE_ROLE FOREIGN KEY (user_role_id) REFERENCES app_user_role (id) ON DELETE CASCADE');
        }

        $this->addSql('ALTER TABLE app_user ADD user_group_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD CONSTRAINT FK_APP_USER_GROUP FOREIGN KEY (user_group_id) REFERENCES app_user_group (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_APP_USER_GROUP ON app_user (user_group_id)');

        $this->addSql("INSERT INTO app_user_role (name, code, created_at, updated_at) VALUES
            ('Super Admin', 'ROLE_SUPER_ADMIN', NOW(), NOW()),
            ('Admin', 'ROLE_ADMIN', NOW(), NOW()),
            ('Client', 'ROLE_CLIENT', NOW(), NOW()),
            ('User', 'ROLE_USER', NOW(), NOW())");

        $this->addSql("INSERT INTO app_user_group (name, code, created_at, updated_at) VALUES
            ('Super Administrator', 'super_administrator', NOW(), NOW()),
            ('Administrator', 'administrator', NOW(), NOW()),
            ('Client', 'client', NOW(), NOW())");

        $this->addSql("INSERT INTO app_user_group_role (user_group_id, user_role_id)
            SELECT g.id, r.id FROM app_user_group g
            INNER JOIN app_user_role r ON (
                (g.code = 'super_administrator' AND r.code IN ('ROLE_SUPER_ADMIN', 'ROLE_USER'))
                OR (g.code = 'administrator' AND r.code IN ('ROLE_ADMIN', 'ROLE_USER'))
                OR (g.code = 'client' AND r.code IN ('ROLE_CLIENT', 'ROLE_USER'))
            )");

        $this->addSql("UPDATE app_user u
            INNER JOIN app_user_group g ON g.code = 'client'
            SET u.user_group_id = g.id
            WHERE u.user_group_id IS NULL AND u.id <> 1");

        $this->addSql("UPDATE app_user u
            INNER JOIN app_user_group g ON g.code = 'super_administrator'
            SET u.user_group_id = g.id
            WHERE u.id = 1");

        $this->addSql("UPDATE app_user u
            INNER JOIN app_user_group g ON g.id = u.user_group_id
            SET u.roles = CASE g.code
                WHEN 'super_administrator' THEN '[\"ROLE_SUPER_ADMIN\",\"ROLE_USER\"]'
                WHEN 'administrator' THEN '[\"ROLE_ADMIN\",\"ROLE_USER\"]'
                ELSE '[\"ROLE_CLIENT\",\"ROLE_USER\"]'
            END
            WHERE u.user_group_id IS NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP FOREIGN KEY FK_APP_USER_GROUP');
        $this->addSql('DROP INDEX IDX_APP_USER_GROUP ON app_user');
        $this->addSql('ALTER TABLE app_user DROP user_group_id');
        $this->addSql('ALTER TABLE app_user_group_role DROP FOREIGN KEY FK_USER_GROUP_ROLE_GROUP');
        $this->addSql('ALTER TABLE app_user_group_role DROP FOREIGN KEY FK_USER_GROUP_ROLE_ROLE');
        $this->addSql('DROP TABLE app_user_group_role');
        $this->addSql('DROP TABLE app_user_group');
        $this->addSql('DROP TABLE app_user_role');
    }
}
