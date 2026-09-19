<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919171000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate bank email parameters into shop_bank_account rows';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('shop_email_parameter') || !$schema->hasTable('shop_bank_account')) {
            return;
        }

        $rows = $this->connection->fetchAllAssociative(
            "SELECT site_id, locale_id, name, value FROM shop_email_parameter WHERE name IN ('shop_iban', 'shop_bank_name', 'shop_bank_recipient', 'shop_edrpou')",
        );

        /** @var array<string, array{site_id: int, locale_id: int, shop_iban: string, shop_bank_name: string, shop_bank_recipient: string, shop_edrpou: string}> $grouped */
        $grouped = [];
        foreach ($rows as $row) {
            $key = $row['site_id'] . ':' . $row['locale_id'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'site_id' => (int) $row['site_id'],
                    'locale_id' => (int) $row['locale_id'],
                    'shop_iban' => '',
                    'shop_bank_name' => '',
                    'shop_bank_recipient' => '',
                    'shop_edrpou' => '',
                ];
            }

            $name = (string) $row['name'];
            if (array_key_exists($name, $grouped[$key])) {
                $grouped[$key][$name] = trim((string) ($row['value'] ?? ''));
            }
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        foreach ($grouped as $item) {
            $exists = $this->connection->fetchOne(
                'SELECT id FROM shop_bank_account WHERE site_id = ? AND locale_id = ?',
                [$item['site_id'], $item['locale_id']],
            );
            if ($exists !== false) {
                continue;
            }

            $recipient = $item['shop_bank_recipient'];
            $title = $recipient !== '' ? $recipient : 'Основний рахунок';

            $this->connection->insert('shop_bank_account', [
                'site_id' => $item['site_id'],
                'locale_id' => $item['locale_id'],
                'title' => $title,
                'iban' => strtoupper(preg_replace('/\s+/', '', $item['shop_iban']) ?? ''),
                'bank_name' => $item['shop_bank_name'],
                'recipient' => $recipient,
                'edrpou' => $item['shop_edrpou'],
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->addSql("DELETE FROM shop_email_parameter WHERE name IN ('shop_iban', 'shop_bank_name', 'shop_bank_recipient', 'shop_edrpou')");
    }

    public function down(Schema $schema): void
    {
    }
}
