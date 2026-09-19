<?php
namespace App\Repository;
use App\Entity\SeoSettingsTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeoSettingsTranslation>
*/
class SeoSettingsTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeoSettingsTranslation::class);
    }
}
