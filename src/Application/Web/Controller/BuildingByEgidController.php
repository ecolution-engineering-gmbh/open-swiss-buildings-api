<?php

declare(strict_types=1);

namespace App\Application\Web\Controller;

use App\Domain\BuildingData\Repository\BuildingAddressMappingRepository;
use App\Domain\BuildingData\Repository\BuildingMetadataRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BuildingByEgidController extends AbstractController
{
    public function __construct(
        private readonly BuildingMetadataRepository $buildingMetadataRepository,
        private readonly BuildingAddressMappingRepository $mappingRepository,
    ) {}

    /**
     * Get complete building metadata by EGID (Federal Building Identifier)
     *
     * Returns comprehensive building data including construction details, energy systems,
     * physical characteristics, and all associated addresses/entrances.
     */
    #[Route('/buildings/egid/{egid}', methods: ['GET'])]
    #[OA\Parameter(
        name: 'egid',
        description: 'Federal Building Identifier (EGID) - 9 digit identifier',
        in: 'path',
        required: true,
        example: '150404'
    )]
    #[OA\Response(
        response: '200',
        description: 'Returns complete building metadata with all addresses',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                'egid' => new OA\Property(property: 'egid', type: 'string', example: '150404'),
                'status' => new OA\Property(property: 'status', type: 'string', example: 'existing'),
                'construction' => new OA\Property(
                    property: 'construction',
                    type: 'object',
                    properties: [
                        'year' => new OA\Property(property: 'year', type: 'string', example: '1995'),
                        'month' => new OA\Property(property: 'month', type: 'string', example: '06'),
                        'period' => new OA\Property(property: 'period', type: 'string', example: '8016'),
                        'category' => new OA\Property(property: 'category', type: 'string', example: '1040'),
                        'class' => new OA\Property(property: 'class', type: 'string', example: '1271'),
                    ]
                ),
                'physicalCharacteristics' => new OA\Property(
                    property: 'physicalCharacteristics',
                    type: 'object',
                    properties: [
                        'area' => new OA\Property(property: 'area', type: 'string', example: '450'),
                        'volume' => new OA\Property(property: 'volume', type: 'string', example: '1580'),
                        'floors' => new OA\Property(property: 'floors', type: 'string', example: '4'),
                        'apartments' => new OA\Property(property: 'apartments', type: 'string', example: '8'),
                    ]
                ),
                'energySystems' => new OA\Property(
                    property: 'energySystems',
                    type: 'object',
                    properties: [
                        'referenceArea' => new OA\Property(property: 'referenceArea', type: 'string', example: '420'),
                        'heating' => new OA\Property(
                            property: 'heating',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    'heatGenerator' => new OA\Property(property: 'heatGenerator', type: 'string', example: '7410'),
                                    'energySource' => new OA\Property(property: 'energySource', type: 'string', example: '7598'),
                                    'lastUpdated' => new OA\Property(property: 'lastUpdated', type: 'string', example: '2021-12-31'),
                                ]
                            )
                        ),
                    ]
                ),
                'addresses' => new OA\Property(
                    property: 'addresses',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            'entranceId' => new OA\Property(property: 'entranceId', type: 'string', example: '0'),
                            'streetAddress' => new OA\Property(property: 'streetAddress', type: 'string', example: 'Limmatstrasse 112'),
                            'postalCode' => new OA\Property(property: 'postalCode', type: 'string', example: '8005'),
                            'locality' => new OA\Property(property: 'locality', type: 'string', example: 'Zürich'),
                            'isPrimary' => new OA\Property(property: 'isPrimary', type: 'boolean', example: true),
                        ]
                    )
                ),
            ]
        )
    )]
    #[OA\Response(response: '404', description: 'Building not found')]
    #[OA\Tag(name: 'Building Metadata')]
    public function __invoke(string $egid): Response
    {
        // Find building metadata
        $buildingMetadata = $this->buildingMetadataRepository->findByEgid($egid);
        
        if (null === $buildingMetadata) {
            return new JsonResponse(['error' => 'Building not found'], Response::HTTP_NOT_FOUND);
        }

        // Get all addresses/entrances for this building
        $addressMappings = $this->mappingRepository->findEntrancesByEgid($egid);

        // Build comprehensive response
        $response = [
            'egid' => $buildingMetadata->egid,
            'egrid' => $buildingMetadata->egrid,
            'status' => $this->mapBuildingStatus($buildingMetadata->gstat),
            'construction' => [
                'year' => $buildingMetadata->gbauj,
                'month' => $buildingMetadata->gbaum,
                'period' => $buildingMetadata->gbaup,
                'demolitionYear' => $buildingMetadata->gabbj ?: null,
                'category' => $buildingMetadata->gkat,
                'class' => $buildingMetadata->gklas,
            ],
            'physicalCharacteristics' => [
                'area' => $buildingMetadata->garea,
                'volume' => $buildingMetadata->gvol,
                'volumeNorm' => $buildingMetadata->gvolnorm,
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
                        'informationSource' => $buildingMetadata->gwaersceh1,
                        'lastUpdated' => $buildingMetadata->gwaerdath1->format('Y-m-d'),
                    ],
                    $buildingMetadata->gwaerzh2 ? [
                        'heatGenerator' => $buildingMetadata->gwaerzh2,
                        'energySource' => $buildingMetadata->genh2,
                        'informationSource' => $buildingMetadata->gwaersceh2,
                        'lastUpdated' => $buildingMetadata->gwaerdath2->format('Y-m-d'),
                    ] : null,
                ]),
                'hotWater' => array_filter([
                    [
                        'heatGenerator' => $buildingMetadata->gwaerzw1,
                        'energySource' => $buildingMetadata->genw1,
                        'informationSource' => $buildingMetadata->gwaerscew1,
                        'lastUpdated' => $buildingMetadata->gwaerdatw1->format('Y-m-d'),
                    ],
                    $buildingMetadata->gwaerzw2 ? [
                        'heatGenerator' => $buildingMetadata->gwaerzw2,
                        'energySource' => $buildingMetadata->genw2,
                        'informationSource' => $buildingMetadata->gwaerscew2,
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
                    'source' => $buildingMetadata->gksce,
                ],
            ],
            'property' => [
                'egrid' => $buildingMetadata->egrid,
                'landRegistryDistrict' => $buildingMetadata->lgbkr,
                'plotNumber' => $buildingMetadata->lparz,
                'plotSuffix' => $buildingMetadata->lparzsx,
                'propertyType' => $buildingMetadata->ltyp,
            ],
            'officialNumber' => $buildingMetadata->gebnr,
            'buildingName' => $buildingMetadata->gbez,
            'addresses' => [],
            'lastExport' => $buildingMetadata->gexpdat->format('Y-m-d'),
        ];

        // Add addresses
        foreach ($addressMappings as $mapping) {
            if ($mapping->buildingEntrance) {
                $entrance = $mapping->buildingEntrance;
                $response['addresses'][] = [
                    'entranceId' => $mapping->entranceId,
                    'streetAddress' => trim($entrance->getStreetName() . ' ' . $entrance->getEntranceNumber()),
                    'postalCode' => $entrance->getLocationZipCode(),
                    'locality' => $entrance->getLocationName(),
                    'canton' => $entrance->getMunicipality(),
                    'isPrimary' => $mapping->isPrimaryEntrance,
                    'coordinates' => $entrance->getGeoCoordinatesWgs84() ? [
                        'latitude' => $entrance->getGeoCoordinatesWgs84()->getLatitude(),
                        'longitude' => $entrance->getGeoCoordinatesWgs84()->getLongitude(),
                        'system' => 'WGS84',
                    ] : null,
                ];
            }
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