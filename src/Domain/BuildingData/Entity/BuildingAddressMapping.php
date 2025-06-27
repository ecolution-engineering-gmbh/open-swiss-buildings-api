<?php

declare(strict_types=1);

namespace App\Domain\BuildingData\Entity;

use App\Domain\BuildingData\Repository\BuildingAddressMappingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Table(name: 'building_address_mapping')]
#[ORM\Entity(repositoryClass: BuildingAddressMappingRepository::class)]
class BuildingAddressMapping
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public Uuid $id;

    /**
     * Eidgenössischer Gebäudeidentifikator (Federal Building Identifier)
     */
    #[ORM\Column(name: 'egid', length: 9)]
    public string $egid;

    /**
     * Building entrance reference
     */
    #[ORM\Column(name: 'building_entrance_id', type: 'uuid')]
    public Uuid $buildingEntranceId;

    /**
     * Eidgenössischer Eingangsidentifikator (Federal Entrance Identifier)
     */
    #[ORM\Column(name: 'entrance_id', length: 2)]
    public string $entranceId;

    /**
     * Whether this is the primary entrance for the building
     */
    #[ORM\Column(name: 'is_primary_entrance', type: 'boolean', options: ['default' => false])]
    public bool $isPrimaryEntrance = false;

    /**
     * Record creation timestamp
     */
    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    public \DateTimeImmutable $createdAt;

    /**
     * Building metadata relationship
     */
    #[ORM\ManyToOne(targetEntity: BuildingMetadata::class)]
    #[ORM\JoinColumn(name: 'egid', referencedColumnName: 'egid', onDelete: 'CASCADE')]
    public ?BuildingMetadata $buildingMetadata = null;

    /**
     * Building entrance relationship
     */
    #[ORM\ManyToOne(targetEntity: BuildingEntrance::class)]
    #[ORM\JoinColumn(name: 'building_entrance_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    public ?BuildingEntrance $buildingEntrance = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
    }
}