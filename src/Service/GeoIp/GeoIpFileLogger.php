<?php

namespace App\Service\GeoIp;

class GeoIpFileLogger
{
    public function __construct(
        private readonly string $logFile,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function log(string $event, array $context = []): void
    {
        $entry = [
            'time' => (new \DateTimeImmutable())->format('c'),
            'event' => $event,
        ] + $context;

        try {
            $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        $directory = \dirname($this->logFile);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        file_put_contents($this->logFile, $line.PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
