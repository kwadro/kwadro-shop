<?php

namespace App\Repository;

use App\Entity\RequestList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RequestList> */
class RequestListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
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
        $sql = <<<'SQL'
            SELECT DATE(r.created_at) AS day_date,
                   COUNT(r.id) AS requests,
                   SUM(
                       CASE
                           WHEN LOWER(COALESCE(r.user_agent, '')) LIKE :googlePattern THEN 1
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
            'googlePattern' => '%google.com%',
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
}
