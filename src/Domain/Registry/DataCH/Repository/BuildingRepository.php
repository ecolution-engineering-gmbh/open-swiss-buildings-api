<?php

declare(strict_types=1);

namespace App\Domain\Registry\DataCH\Repository;

use App\Domain\Registry\DataCH\Entity\Building;
use App\Domain\Registry\DataCH\Model\SwissBuildingStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Building>
 */
final class BuildingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Building::class);
    }

    /**
     * Count active buildings (existing status)
     */
    public function countActiveBuildings(): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.EGID)')
            ->where('b.GSTAT = :status')
            ->setParameter('status', SwissBuildingStatusEnum::EXISTING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find active buildings with pagination
     */
    public function findActiveBuildings(int $limit, int $offset = 0): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.GSTAT = :status')
            ->setParameter('status', SwissBuildingStatusEnum::EXISTING)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy('b.EGID', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find building by EGID
     */
    public function findByEgid(string $egid): ?Building
    {
        return $this->findOneBy(['EGID' => $egid]);
    }
}
