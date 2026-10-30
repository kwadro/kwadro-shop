<?php

namespace App\AiContentAgent\Dto;

final readonly class ContentAgentRequest
{
    /**
     * @param list<DiscoveredSource> $sources
     */
    public function __construct(
        public string $topicTitle,
        public array $sources,
        public \DateTimeImmutable $scheduledAt,
        public bool $scheduleOnly,
        public int $limit,
        public bool $retryFailed,
        public ?bool $deploy,
    ) {
    }
}
