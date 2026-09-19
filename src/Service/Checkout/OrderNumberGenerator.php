<?php

namespace App\Service\Checkout;

use Doctrine\DBAL\Connection;

class OrderNumberGenerator
{
    public const PREFIX = 'KV-';
    public const NUMBER_LENGTH = 6;

    private const SEQUENCE_ID = 1;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function generateNext(): string
    {
        $this->connection->beginTransaction();

        try {
            $lastNumber = (int) $this->connection->fetchOne(
                'SELECT last_number FROM shop_order_number_sequence WHERE id = :id FOR UPDATE',
                ['id' => self::SEQUENCE_ID],
            );

            $nextNumber = max(0, $lastNumber) + 1;

            $this->connection->executeStatement(
                'UPDATE shop_order_number_sequence SET last_number = :lastNumber WHERE id = :id',
                ['lastNumber' => $nextNumber, 'id' => self::SEQUENCE_ID],
            );

            $this->connection->commit();

            return self::format($nextNumber);
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }
    }

    public static function format(int $number): string
    {
        return self::PREFIX . str_pad((string) $number, self::NUMBER_LENGTH, '0', STR_PAD_LEFT);
    }

    public static function isValid(string $orderNumber): bool
    {
        return preg_match('/^' . preg_quote(self::PREFIX, '/') . '\d{' . self::NUMBER_LENGTH . '}$/', $orderNumber) === 1;
    }
}
