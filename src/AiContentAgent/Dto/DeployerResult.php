<?php

namespace App\AiContentAgent\Dto;

final readonly class DeployerResult
{
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        public string $status,
        public string $task,
        public string $host,
        public string $output = '',
        public string $error = '',
    ) {
    }

    public function allowsNotification(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED || $this->status === self::STATUS_SKIPPED;
    }
}
