<?php

namespace App\Service\ProductImport;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Starts a product import outside the web request so PHP-FPM and the session stay free.
 */
final class ProductImportProcessLauncher
{
    public function __construct(
        private readonly ProductImportRunLock $runLock,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @param int $importId
     * @return 'started'|'busy'|'failed'
     */
    public function start(int $importId): string
    {
        if ($importId < 1) {
            return 'failed';
        }
        if ($this->runLock->isBusy($importId)) {
            return 'busy';
        }

        $logDir = $this->projectDir.'/var/log';
        if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            return 'failed';
        }

        $php = $this->phpCliBinary();
        $console = $this->projectDir.'/bin/console';
        $log = $logDir.'/product-import-'.$importId.'.log';
        $command = sprintf(
            'setsid %s %s app:product-import:run --id=%d --no-images >> %s 2>&1 < /dev/null & echo $!',
            escapeshellarg($php),
            escapeshellarg($console),
            $importId,
            escapeshellarg($log),
        );

        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);
        $pid = (int) ($output[0] ?? 0);
        if ($exitCode !== 0 || $pid < 1) {
            return 'failed';
        }

        return 'started';
    }

    private function phpCliBinary(): string
    {
        $binary = PHP_BINARY;
        if (!str_contains($binary, 'fpm') && is_executable($binary)) {
            return $binary;
        }

        foreach ([PHP_BINDIR.'/php', '/usr/bin/php'] as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return 'php';
    }
}
