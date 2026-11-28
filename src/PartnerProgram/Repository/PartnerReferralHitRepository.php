<?php

namespace App\PartnerProgram\Repository;

use App\PartnerProgram\Entity\PartnerReferralHit;
use App\PartnerProgram\Service\AdClickAttribution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PartnerReferralHit> */
class PartnerReferralHitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PartnerReferralHit::class);
    }

    /**
     * Daily totals for partner landings, split by ad source.
     *
     * @return array{
     *     rows: list<array{day: string, total: int, facebook: int, google_ads: int, other: int}>,
     *     totals: array{total: int, facebook: int, google_ads: int, other: int}
     * }
     */
    public function getDailyAdSourceStats(int $days): array
    {
        $days = max(1, min(90, $days));
        $from = (new \DateTimeImmutable('today'))->modify(sprintf('-%d days', $days - 1));

        /** @var list<PartnerReferralHit> $hits */
        $hits = $this->createQueryBuilder('h')
            ->andWhere('h.created_at >= :from')
            ->setParameter('from', $from)
            ->orderBy('h.created_at', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var array<string, array{day: string, total: int, facebook: int, google_ads: int, other: int}> $byDay */
        $byDay = [];
        $totals = ['total' => 0, 'facebook' => 0, 'google_ads' => 0, 'other' => 0];

        for ($i = 0; $i < $days; ++$i) {
            $day = $from->modify(sprintf('+%d days', $i))->format('Y-m-d');
            $byDay[$day] = [
                'day' => $day,
                'total' => 0,
                'facebook' => 0,
                'google_ads' => 0,
                'other' => 0,
            ];
        }

        foreach ($hits as $hit) {
            $createdAt = $hit->getCreatedAt();
            if (!$createdAt instanceof \DateTimeImmutable) {
                continue;
            }
            $day = $createdAt->format('Y-m-d');
            if (!isset($byDay[$day])) {
                continue;
            }

            ++$byDay[$day]['total'];
            ++$totals['total'];

            $source = $hit->getAdSource();
            if ($source === AdClickAttribution::SOURCE_FACEBOOK) {
                ++$byDay[$day]['facebook'];
                ++$totals['facebook'];
            } elseif ($source === AdClickAttribution::SOURCE_GOOGLE_ADS) {
                ++$byDay[$day]['google_ads'];
                ++$totals['google_ads'];
            } else {
                ++$byDay[$day]['other'];
                ++$totals['other'];
            }
        }

        return [
            'rows' => array_values($byDay),
            'totals' => $totals,
        ];
    }
}
