<?php

namespace App\AiContentAgent;

/**
 * Audit actions written by the AI Content Agent.
 */
final class ContentAgentAction
{
    public const RUN_STARTED = 'run.started';
    public const RUN_FINISHED = 'run.finished';
    public const RUN_FAILED = 'run.failed';
    public const SOURCE_RECORDED = 'source.recorded';
    public const SOURCE_DUPLICATE = 'source.duplicate';
    public const SOURCE_REJECTED = 'source.rejected';
    public const TOPIC_SKIPPED = 'topic.skipped';
    public const TOPIC_REGISTERED = 'topic.registered';
    public const QUEUE_ENQUEUED = 'queue.enqueued';
    public const QUEUE_DUPLICATE = 'queue.duplicate';
    public const QUEUE_EMPTY = 'queue.empty';
    public const ARTICLE_GENERATING = 'article.generating';
    public const ARTICLE_GENERATED = 'article.generated';
    public const ARTICLE_PUBLISHED = 'article.published';
    public const TOPIC_PROCESSED = 'topic.processed';
    public const ITEM_FAILED = 'item.failed';
    public const DEPLOYER_STARTED = 'deployer.started';
    public const DEPLOYER_SKIPPED = 'deployer.skipped';
    public const DEPLOYER_SUCCEEDED = 'deployer.succeeded';
    public const DEPLOYER_FAILED = 'deployer.failed';
    public const NOTIFY_SENT = 'notify.sent';
    public const NOTIFY_FAILED = 'notify.failed';

    private function __construct()
    {
    }
}
