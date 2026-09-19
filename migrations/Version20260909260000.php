<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909260000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove duplicated standalone shipment addresses when an order-linked copy exists';
    }

    public function up(Schema $schema): void
    {
    }

    public function down(Schema $schema): void
    {
    }

    public function postUp(Schema $schema): void
    {
        if (!$schema->hasTable('shop_shipment_address')) {
            return;
        }

        $catalogRows = $this->connection->fetchAllAssociative(
            'SELECT id, user_id, type, courier_address, np_city_ref, np_warehouse_ref
             FROM shop_shipment_address
             WHERE user_id IS NOT NULL AND cart_id IS NULL AND order_id IS NULL'
        );

        foreach ($catalogRows as $catalogRow) {
            $orderRowId = $this->findMatchingOrderAddressId($catalogRow);
            if ($orderRowId === null) {
                continue;
            }

            $this->connection->executeStatement(
                'UPDATE shop_shipment_address SET user_id = :userId WHERE id = :id AND user_id IS NULL',
                [
                    'userId' => $catalogRow['user_id'],
                    'id' => $orderRowId,
                ],
            );

            $this->connection->delete('shop_shipment_address', ['id' => $catalogRow['id']]);
        }
    }

    /** @param array<string, mixed> $catalogRow */
    private function findMatchingOrderAddressId(array $catalogRow): ?int
    {
        $userId = (int) ($catalogRow['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $orderRows = $this->connection->fetchAllAssociative(
            'SELECT id, type, courier_address, np_city_ref, np_warehouse_ref
             FROM shop_shipment_address
             WHERE order_id IS NOT NULL AND cart_id IS NULL',
        );

        foreach ($orderRows as $orderRow) {
            if (($orderRow['type'] ?? '') !== ($catalogRow['type'] ?? '')) {
                continue;
            }

            if (($catalogRow['type'] ?? '') === 'courier') {
                if ($this->normalizeText($orderRow['courier_address']) === $this->normalizeText($catalogRow['courier_address'])) {
                    return (int) $orderRow['id'];
                }

                continue;
            }

            if (($orderRow['np_city_ref'] ?? '') === ($catalogRow['np_city_ref'] ?? '')
                && ($orderRow['np_warehouse_ref'] ?? '') === ($catalogRow['np_warehouse_ref'] ?? '')) {
                return (int) $orderRow['id'];
            }
        }

        return null;
    }

    private function normalizeText(mixed $value): string
    {
        if (!\is_string($value) && !is_numeric($value)) {
            return '';
        }

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''));
    }
}
