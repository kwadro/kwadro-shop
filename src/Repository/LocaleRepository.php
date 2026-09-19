<?php
namespace App\Repository;
use App\Entity\Locale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Locale>
*/
class LocaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Locale::class);
    }

    /**
     * @param list<string> $enabledLocales
     *
     * @return list<string>
     */
    public function findEnabledCodes(array $enabledLocales): array
    {
        return array_column($this->findEnabledLocales($enabledLocales), 'code');
    }

    /**
     * @param list<string> $enabledLocales
     *
     * @return list<array{code: string, name: string}>
     */
    public function findEnabledLocales(array $enabledLocales): array
    {
        if ($enabledLocales === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('l')
            ->select('l.code AS code', 'l.name AS name')
            ->where('l.code IN (:codes)')
            ->setParameter('codes', $enabledLocales)
            ->orderBy('l.code', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $locales = [];

        foreach ($rows as $row) {
            if (!\is_string($row['code'] ?? null) || $row['code'] === '') {
                continue;
            }

            $locales[] = [
                'code' => $row['code'],
                'name' => \is_string($row['name'] ?? null) && $row['name'] !== ''
                    ? $row['name']
                    : strtoupper($row['code']),
            ];
        }

        return $locales;
    }
}
