<?php

declare(strict_types=1);

namespace App\Domain\BuildingData\Repository;

use App\Domain\BuildingData\Entity\BuildingMetadata;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BuildingMetadata>
 */
class BuildingMetadataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BuildingMetadata::class);
    }

    /**
     * Find building metadata by EGID
     */
    public function findByEgid(string $egid): ?BuildingMetadata
    {
        return $this->findOneBy(['egid' => $egid]);
    }

    /**
     * Find building metadata by EGRID
     */
    public function findByEgrid(string $egrid): ?BuildingMetadata
    {
        return $this->findOneBy(['egrid' => $egrid]);
    }

    /**
     * Find buildings by municipality
     */
    public function findByMunicipality(string $municipalityCode, int $limit = 10): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.ggdenr = :municipalityCode')
            ->setParameter('municipalityCode', $municipalityCode)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find buildings by canton
     */
    public function findByCanton(string $canton, int $limit = 10): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.gdekt = :canton')
            ->setParameter('canton', $canton)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find buildings by status
     */
    public function findByStatus(string $status, int $limit = 10): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.gstat = :status')
            ->setParameter('status', $status)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find buildings by construction year range
     */
    public function findByConstructionYearRange(string $fromYear, string $toYear, int $limit = 10): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.gbauj >= :fromYear')
            ->andWhere('b.gbauj <= :toYear')
            ->setParameter('fromYear', $fromYear)
            ->setParameter('toYear', $toYear)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find buildings by category
     */
    public function findByCategory(string $category, int $limit = 10): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.gkat = :category')
            ->setParameter('category', $category)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Save building metadata
     */
    public function save(BuildingMetadata $buildingMetadata): void
    {
        $this->getEntityManager()->persist($buildingMetadata);
        $this->getEntityManager()->flush();
    }

    /**
     * Count total buildings
     */
    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.egid)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}