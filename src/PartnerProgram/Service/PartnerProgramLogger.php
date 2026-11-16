<?php

namespace App\PartnerProgram\Service;

/**
 * Writes partner-program diagnostics to var/log/partner-program.log.
 *
 * The default Symfony LoggerInterface writes to stderr and ignores info()
 * (min level ERROR), so partner tracking needs an explicit file logger.
 */
final class PartnerProgramLogger
{
    public function __construct(
        private readonly string $logFile,
    ) {
    }

    /**
     * @param array $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    /**
     * @param array $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    /**
     * @param array $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /**
     * @param array $context
     */
    private function write(string $level, string $message, array $context): void
    {
        $entry = [
            'time' => (new \DateTimeImmutable())->format('c'),
            'level' => $level,
            'message' => $message,
        ];
        if ($context !== []) {
            $entry['context'] = $context;
        }

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
