<?php

namespace App\AiContentAgent\Service;

use Doctrine\DBAL\Connection;

/**
 * Writes every agent action to a JSON line file and to ai_content_log.
 * Logging failures are swallowed so they cannot abort a publication.
 */
final class ContentAgentLogger
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $logFile,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function info(string $action, string $message, array $context = [], ?int $queueItemId = null): void
    {
        $this->write('info', $action, $message, $context, $queueItemId);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $action, string $message, array $context = [], ?int $queueItemId = null): void
    {
        $this->write('warning', $action, $message, $context, $queueItemId);
    }

    /** @param array<string, mixed> $context */
    public function error(string $action, string $message, array $context = [], ?int $queueItemId = null): void
    {
        $this->write('error', $action, $message, $context, $queueItemId);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $action, string $message, array $context, ?int $queueItemId): void
    {
        $now = new \DateTimeImmutable();
        $this->writeFile($now, $level, $action, $message, $context, $queueItemId);
        $this->writeDatabase($now, $level, $action, $message, $context, $queueItemId);
    }

    /** @param array<string, mixed> $context */
    private function writeFile(
        \DateTimeImmutable $now,
        string $level,
        string $action,
        string $message,
        array $context,
        ?int $queueItemId,
    ): void {
        try {
            $line = json_encode([
                'time' => $now->format('c'),
                'level' => $level,
                'action' => $action,
                'message' => $message,
                'queueItemId' => $queueItemId,
                'context' => $context,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        $directory = \dirname($this->logFile);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        file_put_contents($this->logFile, $line.PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /** @param array<string, mixed> $context */
    private function writeDatabase(
        \DateTimeImmutable $now,
        string $level,
        string $action,
        string $message,
        array $context,
        ?int $queueItemId,
    ): void {
        try {
            $encoded = $context === []
                ? null
                : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $stamp = $now->format('Y-m-d H:i:s');
            $this->connection->insert('ai_content_log', [
                'level' => $level,
                'action' => substr($action, 0, 64),
                'message' => $message,
                'context' => $encoded,
                'queue_item_id' => $queueItemId,
                'created_at' => $stamp,
                'updated_at' => $stamp,
            ]);
        } catch (\Throwable) {
            // The file log already captured the action. A missing table must not stop the agent.
        }
    }
}
