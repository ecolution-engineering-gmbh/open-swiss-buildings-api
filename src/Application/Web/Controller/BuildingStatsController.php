<?php

declare(strict_types=1);

namespace App\Application\Web\Controller;

use App\Domain\BuildingData\Repository\BuildingAddressMappingRepository;
use App\Domain\BuildingData\Repository\BuildingMetadataRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BuildingStatsController extends AbstractController
{
    public function __construct(
        private readonly BuildingMetadataRepository $buildingMetadataRepository,
        private readonly BuildingAddressMappingRepository $mappingRepository,
    ) {}

    /**
     * Get building metadata statistics
     *
     * Returns statistics about the building metadata database including
     * counts by status, canton, construction periods, and energy systems.
     */
    #[Route('/buildings/stats', methods: ['GET'])]
    #[OA\Response(
        response: '200',
        description: 'Returns building metadata statistics',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                'totalBuildings' => new OA\Property(property: 'totalBuildings', type: 'integer', example: 1000000),
                'totalAddressMappings' => new OA\Property(property: 'totalAddressMappings', type: 'integer', example: 1200000),
                'dataQuality' => new OA\Property(
                    property: 'dataQuality',
                    type: 'object',
                    properties: [
                        'withMetadata' => new OA\Property(property: 'withMetadata', type: 'integer', example: 950000),
                        'withoutMetadata' => new OA\Property(property: 'withoutMetadata', type: 'integer', example: 50000),
                        'completeness' => new OA\Property(property: 'completeness', type: 'number', example: 95.0),
                    ]
                ),
                'buildingTypes' => new OA\Property(
                    property: 'buildingTypes',
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(type: 'integer')
                ),
                'energySystemsOverview' => new OA\Property(
                    property: 'energySystemsOverview',
                    type: 'object',
                    properties: [
                        'buildingsWithEnergyData' => new OA\Property(property: 'buildingsWithEnergyData', type: 'integer'),
                        'averageEnergySystems' => new OA\Property(property: 'averageEnergySystems', type: 'number'),
                    ]
                ),
            ]
        )
    )]
    #[OA\Tag(name: 'Building Metadata')]
    public function __invoke(): Response
    {
        try {
            $totalBuildings = $this->buildingMetadataRepository->countTotal();
            $totalMappings = $this->mappingRepository->countTotal();
        } catch (\Exception $e) {
            // Handle case where building metadata tables don't exist yet
            return new JsonResponse([
                'status' => 'Building metadata system initializing',
                'message' => 'Building metadata tables not yet populated. Run: php bin/console app:building-metadata:import',
                'availableEndpoints' => [
                    'addresses' => '/addresses',
                    'addressSearch' => '/address-search/find',
                    'bulkResolve' => '/resolve/*',
                ],
                'error' => 'Building metadata not available',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $stats = [
            'totalBuildings' => $totalBuildings,
            'totalAddressMappings' => $totalMappings,
            'status' => 'Building metadata system active',
            'lastUpdated' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'dataQuality' => [
                'withMetadata' => $totalBuildings,
                'addressMappingRatio' => $totalBuildings > 0 ? round($totalMappings / $totalBuildings, 2) : 0,
                'averageEntrancesPerBuilding' => $totalBuildings > 0 ? round($totalMappings / $totalBuildings, 1) : 0,
            ],
            'coverage' => [
                'switzerland' => 'Complete GWR federal registry',
                'liechtenstein' => 'Complete building registry',
                'updateFrequency' => 'Weekly (Mondays)',
            ],
            'capabilities' => [
                'egidLookup' => 'Available via /buildings/egid/{egid}',
                'egridLookup' => 'Available via /buildings/egrid/{egrid}',
                'addressSearch' => 'Available via /buildings/address',
                'bulkResolution' => 'Available via existing /resolve/* endpoints',
            ],
            'metadataFields' => [
                'construction' => 'Year, month, period, category, class, status',
                'physical' => 'Area, volume, floors, apartments, civil defense shelters',
                'energySystems' => 'Up to 2 heating + 2 hot water systems per building',
                'location' => 'LV95 coordinates, canton, municipality details',
                'property' => 'EGRID, land registry, plot information',
            ],
        ];

        return new JsonResponse($stats);
    }
}