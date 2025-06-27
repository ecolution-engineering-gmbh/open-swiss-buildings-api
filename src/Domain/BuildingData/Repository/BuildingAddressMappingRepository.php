<?php

declare(strict_types=1);

namespace App\Domain\BuildingData\Repository;

use App\Domain\BuildingData\Entity\BuildingAddressMapping;
use App\Domain\BuildingData\Entity\BuildingEntrance;
use App\Domain\BuildingData\Entity\BuildingMetadata;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<BuildingAddressMapping>
 */
class BuildingAddressMappingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BuildingAddressMapping::class);
    }

    /**
     * Find building entrances by EGID
     */
    public function findEntrancesByEgid(string $egid): array
    {
        return $this->createQueryBuilder('m')
            ->select('m', 'e')
            ->leftJoin('m.buildingEntrance', 'e')
            ->where('m.egid = :egid')
            ->setParameter('egid', $egid)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find building metadata by entrance ID
     */
    public function findBuildingByEntranceId(Uuid $buildingEntranceId): ?BuildingMetadata
    {
        $mapping = $this->createQueryBuilder('m')
            ->select('m', 'b')
            ->leftJoin('m.buildingMetadata', 'b')
            ->where('m.buildingEntranceId = :entranceId')
            ->setParameter('entranceId', $buildingEntranceId)
            ->getQuery()
            ->getOneOrNullResult();

        return $mapping?->buildingMetadata;
    }

    /**
     * Find primary entrance for building
     */
    public function findPrimaryEntranceByEgid(string $egid): ?BuildingEntrance
    {
        $mapping = $this->createQueryBuilder('m')
            ->select('m', 'e')
            ->leftJoin('m.buildingEntrance', 'e')
            ->where('m.egid = :egid')
            ->andWhere('m.isPrimaryEntrance = :isPrimary')
            ->setParameter('egid', $egid)
            ->setParameter('isPrimary', true)
            ->getQuery()
            ->getOneOrNullResult();

        return $mapping?->buildingEntrance;
    }

    /**
     * Create mapping between building and entrance
     */
    public function createMapping(
        string $egid,
        Uuid $buildingEntranceId,
        string $entranceId,
        bool $isPrimary = false
    ): BuildingAddressMapping {
        $mapping = new BuildingAddressMapping();
        $mapping->egid = $egid;
        $mapping->buildingEntranceId = $buildingEntranceId;
        $mapping->entranceId = $entranceId;
        $mapping->isPrimaryEntrance = $isPrimary;

        $this->getEntityManager()->persist($mapping);
        $this->getEntityManager()->flush();

        return $mapping;
    }

    /**
     * Check if mapping exists
     */
    public function mappingExists(string $egid, Uuid $buildingEntranceId): bool
    {
        $count = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.egid = :egid')
            ->andWhere('m.buildingEntranceId = :entranceId')
            ->setParameter('egid', $egid)
            ->setParameter('entranceId', $buildingEntranceId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Set primary entrance for building (ensures only one primary per building)
     */
    public function setPrimaryEntrance(string $egid, Uuid $buildingEntranceId): void
    {
        // First, remove primary flag from all entrances for this building
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.isPrimaryEntrance', ':false')
            ->where('m.egid = :egid')
            ->setParameter('false', false)
            ->setParameter('egid', $egid)
            ->getQuery()
            ->execute();

        // Then set the specified entrance as primary
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.isPrimaryEntrance', ':true')
            ->where('m.egid = :egid')
            ->andWhere('m.buildingEntranceId = :entranceId')
            ->setParameter('true', true)
            ->setParameter('egid', $egid)
            ->setParameter('entranceId', $buildingEntranceId)
            ->getQuery()
            ->execute();
    }

    /**
     * Count mappings for statistics
     */
    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}