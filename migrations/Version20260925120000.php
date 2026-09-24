<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique slug column to shop_supplier';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shop_supplier');

        if (!$table->hasColumn('slug')) {
            $this->addSql('ALTER TABLE shop_supplier ADD slug VARCHAR(255) DEFAULT NULL');
        }

        $rows = $this->connection->fetchAllAssociative('SELECT id, name FROM shop_supplier ORDER BY id ASC');
        $used = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $base = $this->slugify((string) ($row['name'] ?: 'supplier'));
            if ($base === '') {
                $base = 'supplier';
            }

            $slug = $base;
            $suffix = 1;
            while (isset($used[$slug])) {
                $slug = $base . '-' . $suffix;
                ++$suffix;
            }
            $used[$slug] = true;

            $this->addSql('UPDATE shop_supplier SET slug = ? WHERE id = ?', [$slug, $id]);
        }

        $this->addSql('ALTER TABLE shop_supplier MODIFY slug VARCHAR(255) NOT NULL');

        if (!$table->hasIndex('uniq_shop_supplier_slug')) {
            $this->addSql('CREATE UNIQUE INDEX uniq_shop_supplier_slug ON shop_supplier (slug)');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shop_supplier');

        if ($table->hasIndex('uniq_shop_supplier_slug')) {
            $this->addSql('DROP INDEX uniq_shop_supplier_slug ON shop_supplier');
        }
        if ($table->hasColumn('slug')) {
            $this->addSql('ALTER TABLE shop_supplier DROP slug');
        }
    }

    private function slugify(string $value): string
    {
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g', 'д' => 'd', 'е' => 'e', 'є' => 'ye',
            'ж' => 'zh', 'з' => 'z', 'и' => 'y', 'і' => 'i', 'ї' => 'yi', 'й' => 'y', 'к' => 'k', 'л' => 'l',
            'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ь' => '', 'ю' => 'yu',
            'я' => 'ya', 'ы' => 'y', 'э' => 'e', 'ъ' => '',
        ];

        $value = mb_strtolower(trim($value));
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }
}
