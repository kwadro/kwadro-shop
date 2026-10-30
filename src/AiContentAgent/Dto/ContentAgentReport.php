<?php

namespace App\AiContentAgent\Dto;

final class ContentAgentReport
{
    public int $sourcesRecorded = 0;

    public int $sourcesDuplicate = 0;

    public int $topicsSkipped = 0;

    public int $topicsQueued = 0;

    /** @var list<string> */
    public array $published = [];

    /** @var list<string> */
    public array $failures = [];

    public string $deployStatus = '';

    public int $notified = 0;

    /** @var list<string> */
    public array $notes = [];

    public function absorb(self $other): void
    {
        $this->sourcesRecorded += $other->sourcesRecorded;
        $this->sourcesDuplicate += $other->sourcesDuplicate;
        $this->topicsSkipped += $other->topicsSkipped;
        $this->topicsQueued += $other->topicsQueued;
        $this->published = array_merge($this->published, $other->published);
        $this->failures = array_merge($this->failures, $other->failures);
        $this->notified += $other->notified;
        $this->notes = array_merge($this->notes, $other->notes);

        if ($other->deployStatus === '') {
            return;
        }

        if ($this->deployStatus === '' || $other->deployStatus === DeployerResult::STATUS_FAILED) {
            $this->deployStatus = $other->deployStatus;
        }
    }

    public function hasFailures(): bool
    {
        return $this->failures !== [] || $this->deployStatus === DeployerResult::STATUS_FAILED;
    }
}
