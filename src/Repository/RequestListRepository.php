<?php

namespace App\Repository;

use App\Entity\RequestList;
use App\Service\Request\BotUserAgentClassifier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RequestList> */
class RequestListRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly BotUserAgentClassifier $botUserAgentClassifier,
    ) {
        parent::__construct($registry, RequestList::class);
    }

    public function countUniqueIpsToday(?\DateTimeZone $timezone = null): int
    {
        $timezone ??= new \DateTimeZone(date_default_timezone_get() ?: 'Europe/Kyiv');
        $from = (new \DateTimeImmutable('today', $timezone));
        $to = $from->modify('+1 day');

        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.ip)')
            ->andWhere('r.createdAt >= :from')
            ->andWhere('r.createdAt < :to')
            ->andWhere('r.ip IS NOT NULL')
            ->andWhere("r.ip <> ''")
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countToday(?\DateTimeZone $timezone = null): int
    {
        $timezone ??= new \DateTimeZone(date_default_timezone_get() ?: 'Europe/Kyiv');
        $from = (new \DateTimeImmutable('today', $timezone));
        $to = $from->modify('+1 day');

        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.createdAt >= :from')
            ->andWhere('r.createdAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Popular request paths for the admin path select filter.
     *
     * @return list<string>
     */
    public function findPopularPaths(int $limit = 200, int $days = 7, ?\DateTimeZone $timezone = null): array
    {
        $limit = max(1, min(500, $limit));
        $days = max(1, min(90, $days));
        $timezone ??= new \DateTimeZone(date_default_timezone_get() ?: 'Europe/Kyiv');
        $to = (new \DateTimeImmutable('today', $timezone))->modify('+1 day');
        $from = $to->modify(sprintf('-%d days', $days));

        $rows = $this->createQueryBuilder('r')
            ->select('r.path AS path', 'COUNT(r.id) AS hits')
            ->andWhere('r.createdAt >= :from')
            ->andWhere('r.createdAt < :to')
            ->andWhere("r.path <> ''")
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('r.path')
            ->orderBy('hits', 'DESC')
            ->addOrderBy('r.path', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $paths = [];
        foreach ($rows as $row) {
            $path = $row['path'] ?? null;
            if (\is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * Daily request stats for the last N calendar days (including days with zero).
     *
     * @return array{
     *   rows: list<array{date: string, requests: int, googleBotRequests: int, uniqueIps: int, newIps: int}>,
     *   totals: array{requests: int, googleBotRequests: int, uniqueIps: int, newIps: int}
     * }
     */
    public function getDailyIpStats(int $days = 7, ?\DateTimeZone $timezone = null): array
    {
        $days = max(1, min(90, $days));
        $timezone ??= new \DateTimeZone(date_default_timezone_get() ?: 'Europe/Kyiv');
        $today = new \DateTimeImmutable('today', $timezone);
        $from = $today->modify('-'.($days - 1).' days');
        $to = $today->modify('+1 day');
        $fromStr = $from->format('Y-m-d H:i:s');
        $toStr = $to->format('Y-m-d H:i:s');

        $conn = $this->getEntityManager()->getConnection();
        $botCase = $this->buildBotCaseSql('r.user_agent');
        $sql = <<<SQL
            SELECT DATE(r.created_at) AS day_date,
                   COUNT(r.id) AS requests,
                   SUM(
                       CASE
                           WHEN {$botCase} THEN 1
                           ELSE 0
                       END
                   ) AS google_bot_requests,
                   COUNT(DISTINCT CASE
                       WHEN r.ip IS NOT NULL AND r.ip <> '' THEN r.ip
                   END) AS unique_ips
            FROM shop_request_list r
            WHERE r.created_at >= :from
              AND r.created_at < :to
            GROUP BY DATE(r.created_at)
            ORDER BY day_date ASC
        SQL;

        $raw = $conn->fetchAllAssociative($sql, [
            'from' => $fromStr,
            'to' => $toStr,
            ...$this->botPatternParameters(),
        ]);

        $newIpsSql = <<<'SQL'
            SELECT DATE(first_seen) AS day_date,
                   COUNT(*) AS new_ips
            FROM (
                SELECT r.ip AS ip,
                       MIN(r.created_at) AS first_seen
                FROM shop_request_list r
                WHERE r.ip IS NOT NULL
                  AND r.ip <> ''
                GROUP BY r.ip
            ) firsts
            WHERE first_seen >= :from
              AND first_seen < :to
            GROUP BY DATE(first_seen)
        SQL;

        $newIpsRaw = $conn->fetchAllAssociative($newIpsSql, [
            'from' => $fromStr,
            'to' => $toStr,
        ]);

        $newIpsByDate = [];
        foreach ($newIpsRaw as $row) {
            $date = (string) ($row['day_date'] ?? '');
            if ($date === '') {
                continue;
            }
            $newIpsByDate[$date] = (int) ($row['new_ips'] ?? 0);
        }

        $byDate = [];
        foreach ($raw as $row) {
            $date = (string) ($row['day_date'] ?? '');
            if ($date === '') {
                continue;
            }
            $byDate[$date] = [
                'requests' => (int) ($row['requests'] ?? 0),
                'googleBotRequests' => (int) ($row['google_bot_requests'] ?? 0),
                'uniqueIps' => (int) ($row['unique_ips'] ?? 0),
            ];
        }

        $periodUniqueIps = (int) $conn->fetchOne(
            <<<'SQL'
                SELECT COUNT(DISTINCT r.ip)
                FROM shop_request_list r
                WHERE r.created_at >= :from
                  AND r.created_at < :to
                  AND r.ip IS NOT NULL
                  AND r.ip <> ''
            SQL,
            ['from' => $fromStr, 'to' => $toStr],
        );

        $periodNewIps = (int) $conn->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM (
                    SELECT r.ip AS ip,
                           MIN(r.created_at) AS first_seen
                    FROM shop_request_list r
                    WHERE r.ip IS NOT NULL
                      AND r.ip <> ''
                    GROUP BY r.ip
                ) firsts
                WHERE first_seen >= :from
                  AND first_seen < :to
            SQL,
            ['from' => $fromStr, 'to' => $toStr],
        );

        $rows = [];
        $totalRequests = 0;
        $totalGoogle = 0;
        for ($i = 0; $i < $days; ++$i) {
            $date = $from->modify('+'.$i.' days')->format('Y-m-d');
            $requests = $byDate[$date]['requests'] ?? 0;
            $google = $byDate[$date]['googleBotRequests'] ?? 0;
            $uniqueIps = $byDate[$date]['uniqueIps'] ?? 0;
            $newIps = $newIpsByDate[$date] ?? 0;
            $rows[] = [
                'date' => $date,
                'requests' => $requests,
                'googleBotRequests' => $google,
                'uniqueIps' => $uniqueIps,
                'newIps' => $newIps,
            ];
            $totalRequests += $requests;
            $totalGoogle += $google;
        }

        // Newest day first for display.
        $rows = array_reverse($rows);

        return [
            'rows' => $rows,
            'totals' => [
                'requests' => $totalRequests,
                'googleBotRequests' => $totalGoogle,
                'uniqueIps' => $periodUniqueIps,
                'newIps' => $periodNewIps,
            ],
        ];
    }

    /**
     * Non-bot traffic series for the admin chart.
     *
     * Daily buckets when the range spans multiple calendar days; hourly (0–23)
     * when from/to fall on the same calendar day.
     *
     * @return array
     */
    public function getNonBotTrafficSeries(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?\DateTimeZone $timezone = null,
    ): array {
        $timezone ??= new \DateTimeZone(date_default_timezone_get() ?: 'Europe/Kyiv');
        $from = $from->setTimezone($timezone);
        $to = $to->setTimezone($timezone);
        if ($to <= $from) {
            $to = $from->modify('+1 day');
        }

        $lastInstant = $to->modify('-1 second');
        $hourly = $from->format('Y-m-d') === $lastInstant->format('Y-m-d');

        return $hourly
            ? $this->buildHourlyNonBotSeries($from, $to)
            : $this->buildDailyNonBotSeries($from, $to);
    }

    /**
     * @return array
     */
    private function buildDailyNonBotSeries(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $conn = $this->getEntityManager()->getConnection();
        $botCase = $this->buildBotCaseSql('r.user_agent');
        $humanCase = sprintf('NOT (%s)', $botCase);
        $fromStr = $from->format('Y-m-d H:i:s');
        $toStr = $to->format('Y-m-d H:i:s');

        $sql = <<<SQL
            SELECT DATE(r.created_at) AS bucket,
                   SUM(CASE WHEN {$humanCase} THEN 1 ELSE 0 END) AS human_requests,
                   COUNT(DISTINCT CASE
                       WHEN {$humanCase}
                            AND r.ip IS NOT NULL
                            AND r.ip <> ''
                       THEN r.ip
                   END) AS human_unique_ips
            FROM shop_request_list r
            WHERE r.created_at >= :from
              AND r.created_at < :to
            GROUP BY DATE(r.created_at)
            ORDER BY bucket ASC
        SQL;

        $raw = $conn->fetchAllAssociative($sql, [
            'from' => $fromStr,
            'to' => $toStr,
            ...$this->botPatternParameters(),
        ]);

        $byBucket = [];
        foreach ($raw as $row) {
            $bucket = (string) ($row['bucket'] ?? '');
            if ($bucket === '') {
                continue;
            }
            $byBucket[$bucket] = [
                'requests' => (int) ($row['human_requests'] ?? 0),
                'uniqueIps' => (int) ($row['human_unique_ips'] ?? 0),
            ];
        }

        $labels = [];
        $requests = [];
        $uniqueIps = [];
        $cursor = $from->setTime(0, 0);
        $endDay = $to->modify('-1 second')->setTime(0, 0);
        while ($cursor <= $endDay) {
            $key = $cursor->format('Y-m-d');
            $labels[] = $key;
            $requests[] = $byBucket[$key]['requests'] ?? 0;
            $uniqueIps[] = $byBucket[$key]['uniqueIps'] ?? 0;
            $cursor = $cursor->modify('+1 day');
        }

        return [
            'mode' => 'daily',
            'labels' => $labels,
            'requests' => $requests,
            'uniqueIps' => $uniqueIps,
        ];
    }

    /**
     * @return array
     */
    private function buildHourlyNonBotSeries(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $botCase = $this->buildBotCaseSql('r.user_agent');
        $humanCase = sprintf('NOT (%s)', $botCase);
        $fromStr = $from->format('Y-m-d H:i:s');
        $toStr = $to->format('Y-m-d H:i:s');

        $sql = <<<SQL
            SELECT HOUR(r.created_at) AS bucket,
                   SUM(CASE WHEN {$humanCase} THEN 1 ELSE 0 END) AS human_requests,
                   COUNT(DISTINCT CASE
                       WHEN {$humanCase}
                            AND r.ip IS NOT NULL
                            AND r.ip <> ''
                       THEN r.ip
                   END) AS human_unique_ips
            FROM shop_request_list r
            WHERE r.created_at >= :from
              AND r.created_at < :to
            GROUP BY HOUR(r.created_at)
            ORDER BY bucket ASC
        SQL;

        $raw = $conn->fetchAllAssociative($sql, [
            'from' => $fromStr,
            'to' => $toStr,
            ...$this->botPatternParameters(),
        ]);

        $byHour = [];
        foreach ($raw as $row) {
            $hour = (int) ($row['bucket'] ?? -1);
            if ($hour < 0 || $hour > 23) {
                continue;
            }
            $byHour[$hour] = [
                'requests' => (int) ($row['human_requests'] ?? 0),
                'uniqueIps' => (int) ($row['human_unique_ips'] ?? 0),
            ];
        }

        $labels = [];
        $requests = [];
        $uniqueIps = [];
        for ($hour = 0; $hour <= 23; ++$hour) {
            $labels[] = sprintf('%02d:00', $hour);
            $requests[] = $byHour[$hour]['requests'] ?? 0;
            $uniqueIps[] = $byHour[$hour]['uniqueIps'] ?? 0;
        }

        return [
            'mode' => 'hourly',
            'labels' => $labels,
            'requests' => $requests,
            'uniqueIps' => $uniqueIps,
        ];
    }

    private function buildBotCaseSql(string $columnSql): string
    {
        $parts = [];
        foreach (array_keys($this->botPatternParameters()) as $i => $param) {
            $parts[] = sprintf('LOWER(COALESCE(%s, \'\')) LIKE :%s', $columnSql, $param);
        }

        return $parts === [] ? '0 = 1' : implode(' OR ', $parts);
    }

    /**
     * @return array
     */
    private function botPatternParameters(): array
    {
        $params = [];
        foreach ($this->botUserAgentClassifier->sqlLikePatterns() as $i => $pattern) {
            $params['botPattern'.$i] = $pattern;
        }

        return $params;
    }
}
