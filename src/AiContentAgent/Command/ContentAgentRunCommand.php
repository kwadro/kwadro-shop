<?php

namespace App\AiContentAgent\Command;

use App\AiContentAgent\Dto\ContentAgentReport;
use App\AiContentAgent\Dto\ContentAgentRequest;
use App\AiContentAgent\Dto\DeployerResult;
use App\AiContentAgent\Dto\DiscoveredSource;
use App\AiContentAgent\Service\ContentAgent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:content-agent:run',
    description: 'Record sources, queue unpublished topics, and publish due SEO articles',
)]
final class ContentAgentRunCommand extends Command
{
    public function __construct(
        private readonly ContentAgent $agent,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('topic', null, InputOption::VALUE_REQUIRED, 'Topic title. Already processed topics are not generated again')
            ->addOption('source', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Source as url or url|title|excerpt')
            ->addOption('jobs', null, InputOption::VALUE_REQUIRED, 'JSON file with topics, schedules, and sources')
            ->addOption('schedule', null, InputOption::VALUE_REQUIRED, 'When to publish the topic (default: now)')
            ->addOption('schedule-only', null, InputOption::VALUE_NONE, 'Store sources and queue the topic without publishing')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'How many due publications to publish in this run', '1')
            ->addOption('retry-failed', null, InputOption::VALUE_NONE, 'Include failed queue items')
            ->addOption('deploy', null, InputOption::VALUE_NONE, 'Run the Deployer task even when it is disabled in the environment')
            ->addOption('no-deploy', null, InputOption::VALUE_NONE, 'Do not run the Deployer task')
            ->setHelp(<<<'HELP'
The agent stores every accepted source, skips topics that were already processed,
queues future publications, generates an SEO article, publishes it, runs the
configured Deployer task (blog:push by default), writes an audit log, and
announces a successful publication.

Examples:
  php bin/console app:content-agent:run --topic="How to choose a TV mount" --source="https://example.com/guide|Mount guide"
  php bin/console app:content-agent:run --topic="How to choose a TV mount" --schedule="2026-11-02 09:00" --schedule-only
  php bin/console app:content-agent:run --limit=1 --deploy

Set AI_CONTENT_DEPLOY_ENABLED=1 to run the Deployer task on each successful batch.
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('deploy') && $input->getOption('no-deploy')) {
            $io->error('Use either --deploy or --no-deploy.');

            return Command::FAILURE;
        }

        try {
            $scheduledAt = $this->schedule($input->getOption('schedule'));
            $sources = $this->sources($input->getOption('source'));
            $jobs = $this->jobs($input->getOption('jobs'), $scheduledAt);
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $topic = trim((string) $input->getOption('topic'));
        if ($topic === '' && $jobs === [] && $sources !== []) {
            $io->error('Pass --topic when you pass --source.');

            return Command::FAILURE;
        }

        $deploy = null;
        if ($input->getOption('deploy')) {
            $deploy = true;
        } elseif ($input->getOption('no-deploy')) {
            $deploy = false;
        }

        $scheduleOnly = (bool) $input->getOption('schedule-only');
        $limit = (int) $input->getOption('limit');
        $retryFailed = (bool) $input->getOption('retry-failed');
        $intakes = $jobs;
        if ($topic !== '') {
            $intakes[] = new ContentAgentRequest(
                $topic,
                $sources,
                $scheduledAt,
                $scheduleOnly,
                $limit,
                $retryFailed,
                $deploy,
            );
        }

        $report = $this->agent->executeBatch($intakes, $scheduleOnly, $limit, $retryFailed, $deploy);
        $this->render($io, $report);

        return $report->hasFailures() ? Command::FAILURE : Command::SUCCESS;
    }

    private function schedule(mixed $value): \DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return new \DateTimeImmutable();
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Schedule is not a valid date: '.$value);
        }
    }

    /**
     * @param mixed $values
     *
     * @return list<DiscoveredSource>
     */
    private function sources(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $sources = [];
        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            $parts = explode('|', $value, 3);
            $sources[] = new DiscoveredSource(
                trim($parts[0]),
                trim($parts[1] ?? ''),
                trim($parts[2] ?? ''),
            );
        }

        return $sources;
    }

    /**
     * @return list<ContentAgentRequest>
     */
    private function jobs(mixed $path, \DateTimeImmutable $defaultSchedule): array
    {
        if (!is_string($path) || trim($path) === '') {
            return [];
        }

        if (!is_file($path)) {
            throw new \InvalidArgumentException('Jobs file was not found: '.$path);
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Jobs file must contain a JSON array.');
        }

        $jobs = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('Each job must be an object with a topic.');
            }
            $topic = trim((string) ($row['topic'] ?? ''));
            if ($topic === '') {
                throw new \InvalidArgumentException('Each job needs a topic.');
            }

            $sources = [];
            foreach ($row['sources'] ?? [] as $source) {
                if (!is_array($source)) {
                    continue;
                }
                $sources[] = new DiscoveredSource(
                    trim((string) ($source['url'] ?? '')),
                    trim((string) ($source['title'] ?? '')),
                    trim((string) ($source['excerpt'] ?? '')),
                );
            }

            $schedule = $defaultSchedule;
            if (isset($row['schedule']) && is_string($row['schedule']) && trim($row['schedule']) !== '') {
                $schedule = $this->schedule($row['schedule']);
            }

            $jobs[] = new ContentAgentRequest($topic, $sources, $schedule, true, 1, false, null);
        }

        return $jobs;
    }

    private function render(SymfonyStyle $io, ContentAgentReport $report): void
    {
        $io->definitionList(
            ['Sources stored' => (string) $report->sourcesRecorded],
            ['Duplicate sources' => (string) $report->sourcesDuplicate],
            ['Topics queued' => (string) $report->topicsQueued],
            ['Topics skipped' => (string) $report->topicsSkipped],
            ['Articles published' => $report->published === [] ? '0' : implode(', ', $report->published)],
            ['Deployer' => $report->deployStatus !== '' ? $report->deployStatus : 'not run'],
            ['Notifications' => (string) $report->notified],
        );

        foreach ($report->notes as $note) {
            $io->text($note);
        }

        if ($report->failures !== []) {
            $io->error($report->failures);

            return;
        }

        if ($report->deployStatus === DeployerResult::STATUS_FAILED) {
            $io->error('Deployer task failed. The publication was not announced.');

            return;
        }

        $io->success('Content agent finished.');
    }
}
