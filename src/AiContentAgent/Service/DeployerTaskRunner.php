<?php

namespace App\AiContentAgent\Service;

use App\AiContentAgent\ContentAgentAction;
use App\AiContentAgent\Dto\DeployerResult;
use Symfony\Component\Process\Process;

/**
 * Runs one Deployer task from deploy.php. blog:push is the shop blog publish task.
 */
final class DeployerTaskRunner
{
    public function __construct(
        private readonly ContentAgentLogger $logger,
        private readonly string $projectDir,
        private readonly bool $enabled,
        private readonly string $task,
        private readonly string $host,
        private readonly int $timeout = 600,
    ) {
    }

    public function run(?bool $override = null): DeployerResult
    {
        $enabled = $override ?? $this->enabled;
        $task = trim($this->task) !== '' ? trim($this->task) : 'blog:push';
        $host = trim($this->host) !== '' ? trim($this->host) : 'production';

        if (!$enabled) {
            $this->logger->info(ContentAgentAction::DEPLOYER_SKIPPED, 'Deployer task is disabled.', [
                'task' => $task,
                'host' => $host,
            ]);

            return new DeployerResult(DeployerResult::STATUS_SKIPPED, $task, $host);
        }

        $binary = $this->projectDir.'/vendor/bin/dep';
        if (!is_file($binary)) {
            $binary = 'dep';
        }

        $this->logger->info(ContentAgentAction::DEPLOYER_STARTED, 'Deployer task started.', [
            'task' => $task,
            'host' => $host,
            'binary' => $binary,
        ]);

        $process = new Process([$binary, $task, $host], $this->projectDir, null, null, $this->timeout);
        $process->run();
        $output = trim($process->getOutput()."\n".$process->getErrorOutput());
        if (mb_strlen($output) > 4000) {
            $output = mb_substr($output, -4000);
        }

        if (!$process->isSuccessful()) {
            $error = trim($process->getErrorOutput());
            if ($error === '') {
                $error = sprintf('Deployer task %s exited with code %d.', $task, $process->getExitCode() ?? -1);
            }
            $this->logger->error(ContentAgentAction::DEPLOYER_FAILED, $error, [
                'task' => $task,
                'host' => $host,
                'exitCode' => $process->getExitCode(),
                'output' => $output,
            ]);

            return new DeployerResult(DeployerResult::STATUS_FAILED, $task, $host, $output, $error);
        }

        $this->logger->info(ContentAgentAction::DEPLOYER_SUCCEEDED, 'Deployer task finished.', [
            'task' => $task,
            'host' => $host,
            'output' => $output,
        ]);

        return new DeployerResult(DeployerResult::STATUS_SUCCEEDED, $task, $host, $output);
    }
}
