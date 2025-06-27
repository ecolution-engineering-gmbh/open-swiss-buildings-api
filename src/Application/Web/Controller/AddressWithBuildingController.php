<?php

declare(strict_types=1);

namespace App\Application\Web\Controller;

use App\Application\Contract\BuildingAddressFinderInterface;
use App\Domain\BuildingData\Repository\BuildingAddressMappingRepository;
use App\Domain\BuildingData\Repository\BuildingMetadataRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\UuidV7;

final class AddressWithBuildingController extends AbstractController
{
    public function __construct(
        private readonly BuildingAddressFinderInterface $buildingAddressFinder,
        private readonly BuildingAddressMappingRepository $mappingRepository,
        private readonly BuildingMetadataRepository $buildingMetadataRepository,
    ) {}

    /**
     * Returns address details with complete building metadata
     *
     * Enhanced version of /addresses/{id} that includes comprehensive building
     * metadata for the address.
     */
    #[Route('/addresses/{id}/building', methods: ['GET'])]
    #[OA\Parameter(
        name: 'id',
        description: 'Address UUID',
        in: 'path',
        required: true
    )]
    #[OA\Parameter(
        name: 'include_all_entrances',
        description: 'Include all building entrances, not just this address',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'boolean', default: false)
    )]
    #[OA\Response(
        response: '200',
        description: 'Returns address with complete building metadata',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                'address' => new OA\Property(
                    property: 'address',
                    type: 'object',
                    properties: [
                        'id' => new OA\Property(property: 'id', type: 'string'),
                        'streetAddress' => new OA\Property(property: 'streetAddress', type: 'string'),
                        'postalCode' => new OA\Property(property: 'postalCode', type: 'string'),
                        'locality' => new OA\Property(property: 'locality', type: 'string'),
                    ]
                ),
                'building' => new OA\Property(
                    property: 'building',
                    type: 'object',
                    properties: [
                        'egid' => new OA\Property(property: 'egid', type: 'string'),
                        'construction' => new OA\Property(property: 'construction', type: 'object'),
                        'physicalCharacteristics' => new OA\Property(property: 'physicalCharacteristics', type: 'object'),
                        'energySystems' => new OA\Property(property: 'energySystems', type: 'object'),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(response: '404', description: 'Address not found')]
    #[OA\Tag(name: 'Building Metadata')]
    public function __invoke(string $id, Request $request): Response
    {
        try {
            $addressId = new UuidV7($id);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['error' => 'Invalid address ID'], Response::HTTP_NOT_FOUND);
        }

        $includeAllEntrances = $request->query->getBoolean('include_all_entrances', false);

        // Get the address details
        $place = $this->buildingAddressFinder->findPlace($addressId);
        if (null === $place) {
            return new JsonResponse(['error' => 'Address not found'], Response::HTTP_NOT_FOUND);
        }

        // Get building metadata through address mapping
        $buildingMetadata = $this->mappingRepository->findBuildingByEntranceId($addressId);
        if (null === $buildingMetadata) {
            return new JsonResponse([
                'address' => [
                    'id' => $id,
                    'streetAddress' => $place->postalAddress->streetAddress,
                    'postalCode' => $place->postalAddress->postalCode,
                    'locality' => $place->postalAddress->addressLocality,
                    'canton' => $place->postalAddress->addressRegion,
                    'coordinates' => [
                        'latitude' => $place->geo->latitude,
                        'longitude' => $place->geo->longitude,
                    ],
                ],
                'building' => null,
                'note' => 'No building metadata available for this address',
            ]);
        }

        // Build response with address and building data
        $response = [
            'address' => [
                'id' => $id,
                'streetAddress' => $place->postalAddress->streetAddress,
                'postalCode' => $place->postalAddress->postalCode,
                'locality' => $place->postalAddress->addressLocality,
                'canton' => $place->postalAddress->addressRegion,
                'coordinates' => [
                    'latitude' => $place->geo->latitude,
                    'longitude' => $place->geo->longitude,
                ],
            ],
            'building' => [
                'egid' => $buildingMetadata->egid,
                'egrid' => $buildingMetadata->egrid,
                'status' => $this->mapBuildingStatus($buildingMetadata->gstat),
                'construction' => [
                    'year' => $buildingMetadata->gbauj,
                    'month' => $buildingMetadata->gbaum,
                    'category' => $buildingMetadata->gkat,
                    'class' => $buildingMetadata->gklas,
                    'period' => $buildingMetadata->gbaup,
                ],
                'physicalCharacteristics' => [
                    'area' => $buildingMetadata->garea,
                    'volume' => $buildingMetadata->gvol,
                    'floors' => $buildingMetadata->gastw,
                    'apartments' => $buildingMetadata->ganzwhg,
                    'separateRooms' => $buildingMetadata->gazzi,
                    'civilDefenseShelter' => $buildingMetadata->gschutzr === '1',
                ],
                'energySystems' => [
                    'referenceArea' => $buildingMetadata->gebf,
                    'heating' => array_filter([
                        [
                            'heatGenerator' => $buildingMetadata->gwaerzh1,
                            'energySource' => $buildingMetadata->genh1,
                            'lastUpdated' => $buildingMetadata->gwaerdath1->format('Y-m-d'),
                        ],
                        $buildingMetadata->gwaerzh2 ? [
                            'heatGenerator' => $buildingMetadata->gwaerzh2,
                            'energySource' => $buildingMetadata->genh2,
                            'lastUpdated' => $buildingMetadata->gwaerdath2->format('Y-m-d'),
                        ] : null,
                    ]),
                    'hotWater' => array_filter([
                        [
                            'heatGenerator' => $buildingMetadata->gwaerzw1,
                            'energySource' => $buildingMetadata->genw1,
                            'lastUpdated' => $buildingMetadata->gwaerdatw1->format('Y-m-d'),
                        ],
                        $buildingMetadata->gwaerzw2 ? [
                            'heatGenerator' => $buildingMetadata->gwaerzw2,
                            'energySource' => $buildingMetadata->genw2,
                            'lastUpdated' => $buildingMetadata->gwaerdatw2->format('Y-m-d'),
                        ] : null,
                    ]),
                ],
                'location' => [
                    'canton' => $buildingMetadata->gdekt,
                    'municipalityCode' => $buildingMetadata->ggdenr,
                    'municipalityName' => $buildingMetadata->ggdename,
                    'coordinates' => [
                        'east' => $buildingMetadata->gkode,
                        'north' => $buildingMetadata->gkodn,
                        'system' => 'LV95',
                    ],
                ],
                'officialNumber' => $buildingMetadata->gebnr,
                'buildingName' => $buildingMetadata->gbez,
                'lastExport' => $buildingMetadata->gexpdat->format('Y-m-d'),
            ],
        ];

        // Optionally include all entrances for this building
        if ($includeAllEntrances) {
            $entranceMappings = $this->mappingRepository->findEntrancesByEgid($buildingMetadata->egid);
            $allEntrances = [];
            
            foreach ($entranceMappings as $mapping) {
                if ($mapping->buildingEntrance) {
                    $entrance = $mapping->buildingEntrance;
                    $allEntrances[] = [
                        'entranceId' => $mapping->entranceId,
                        'streetAddress' => trim($entrance->getStreetName() . ' ' . $entrance->getEntranceNumber()),
                        'postalCode' => $entrance->getLocationZipCode(),
                        'locality' => $entrance->getLocationName(),
                        'isPrimary' => $mapping->isPrimaryEntrance,
                        'isCurrentAddress' => $entrance->getId()->toString() === $id,
                    ];
                }
            }
            
            $response['building']['allEntrances'] = $allEntrances;
        }

        return new JsonResponse($response);
    }

    private function mapBuildingStatus(string $gstat): string
    {
        return match ($gstat) {
            '1001' => 'planned',
            '1002' => 'approved',
            '1003' => 'under_construction', 
            '1004' => 'existing',
            '1005' => 'not_usable',
            '1007' => 'demolished',
            '1008' => 'not_built',
            default => 'unknown',
        };
    }
}