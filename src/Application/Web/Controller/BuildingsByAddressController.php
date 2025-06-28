<?php

declare(strict_types=1);

namespace App\Application\Web\Controller;

use App\Application\Contract\BuildingAddressSearcherInterface;
use App\Domain\AddressSearch\Model\AddressSearch;
use App\Domain\BuildingData\Repository\BuildingAddressMappingRepository;
use App\Domain\BuildingData\Repository\BuildingMetadataRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BuildingsByAddressController extends AbstractController
{
    public function __construct(
        private readonly BuildingAddressSearcherInterface $addressSearcher,
        private readonly BuildingMetadataRepository $buildingMetadataRepository,
        private readonly BuildingAddressMappingRepository $mappingRepository,
    ) {}

    /**
     * Search buildings by address with complete metadata
     *
     * Allows searching for buildings using various address components and returns
     * complete building metadata for each match.
     */
    #[Route('/buildings/address', methods: ['GET'])]
    #[OA\Parameter(
        name: 'strasse',
        description: 'Street name',
        in: 'query',
        required: false,
        example: 'Limmatstrasse'
    )]
    #[OA\Parameter(
        name: 'hausnummer',
        description: 'House number',
        in: 'query',
        required: false,
        example: '112'
    )]
    #[OA\Parameter(
        name: 'plz',
        description: 'Postal code',
        in: 'query',
        required: false,
        example: '8005'
    )]
    #[OA\Parameter(
        name: 'ort',
        description: 'Municipality/City',
        in: 'query',
        required: false,
        example: 'Zürich'
    )]
    #[OA\Parameter(
        name: 'adresse',
        description: 'Complete address string (alternative to individual components)',
        in: 'query',
        required: false,
        example: 'Limmatstrasse 112, 8005 Zürich'
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Maximum number of results',
        in: 'query',
        required: false,
        example: 10
    )]
    #[OA\Response(
        response: '200',
        description: 'Returns buildings matching the address criteria with metadata',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                'query' => new OA\Property(property: 'query', type: 'string', example: 'Limmatstrasse 112 Zürich'),
                'count' => new OA\Property(property: 'count', type: 'integer', example: 1),
                'buildings' => new OA\Property(
                    property: 'buildings',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            'egid' => new OA\Property(property: 'egid', type: 'string', example: '150404'),
                            'matchedAddress' => new OA\Property(
                                property: 'matchedAddress',
                                type: 'object',
                                properties: [
                                    'streetAddress' => new OA\Property(property: 'streetAddress', type: 'string', example: 'Limmatstrasse 112'),
                                    'postalCode' => new OA\Property(property: 'postalCode', type: 'string', example: '8005'),
                                    'locality' => new OA\Property(property: 'locality', type: 'string', example: 'Zürich'),
                                ]
                            ),
                            'construction' => new OA\Property(property: 'construction', type: 'object'),
                            'physicalCharacteristics' => new OA\Property(property: 'physicalCharacteristics', type: 'object'),
                            'energySystems' => new OA\Property(property: 'energySystems', type: 'object'),
                        ]
                    )
                ),
            ]
        )
    )]
    #[OA\Response(response: '400', description: 'Invalid query parameters')]
    #[OA\Tag(name: 'Building Metadata')]
    public function __invoke(Request $request): Response
    {
        // Extract query parameters
        $strasse = $request->query->get('strasse');
        $hausnummer = $request->query->get('hausnummer');
        $plz = $request->query->get('plz');
        $ort = $request->query->get('ort');
        $adresse = $request->query->get('adresse');
        $limit = (int) ($request->query->get('limit', 10));

        // Validate limit
        if ($limit < 1 || $limit > 100) {
            return new JsonResponse(['error' => 'Limit must be between 1 and 100'], Response::HTTP_BAD_REQUEST);
        }

        // Build search query
        $searchQuery = '';
        if ($adresse) {
            $searchQuery = $adresse;
        } else {
            $parts = array_filter([$strasse, $hausnummer, $plz, $ort]);
            if (empty($parts)) {
                return new JsonResponse(['error' => 'At least one address component is required'], Response::HTTP_BAD_REQUEST);
            }
            $searchQuery = implode(' ', $parts);
        }

        // Search for addresses using existing search system
        $addressSearchFilter = new AddressSearch(
            limit: $limit,
            minScore: 0.0,
            filterByQuery: $searchQuery,
            filterByCountryCodes: null,
        );
        $searchResults = $this->addressSearcher->searchPlaces($addressSearchFilter);

        $buildings = [];
        $processedEgids = [];

        foreach ($searchResults as $placeScored) {
            $place = $placeScored->place;
            // Extract building ID from additional properties
            $buildingId = $place->additionalProperty->buildingId ?? null;
            if (!$buildingId || in_array($buildingId, $processedEgids, true)) {
                continue;
            }

            $processedEgids[] = $buildingId;

            // Get building metadata
            $buildingMetadata = $this->buildingMetadataRepository->findByEgid($buildingId);
            if (!$buildingMetadata) {
                continue;
            }

            // Build comprehensive building response
            $building = [
                'egid' => $buildingMetadata->egid,
                'status' => $this->mapBuildingStatus($buildingMetadata->gstat),
                'matchedAddress' => [
                    'streetAddress' => $place->postalAddress->streetAddress,
                    'postalCode' => $place->postalAddress->postalCode,
                    'locality' => $place->postalAddress->addressLocality,
                    'canton' => $place->postalAddress->addressRegion,
                ],
                'construction' => [
                    'year' => $buildingMetadata->gbauj,
                    'month' => $buildingMetadata->gbaum,
                    'category' => $buildingMetadata->gkat,
                    'class' => $buildingMetadata->gklas,
                ],
                'physicalCharacteristics' => [
                    'area' => $buildingMetadata->garea,
                    'volume' => $buildingMetadata->gvol,
                    'floors' => $buildingMetadata->gastw,
                    'apartments' => $buildingMetadata->ganzwhg,
                ],
                'energySystems' => [
                    'referenceArea' => $buildingMetadata->gebf,
                    'heatingSystemCount' => $buildingMetadata->gwaerzh2 ? 2 : 1,
                    'hotWaterSystemCount' => $buildingMetadata->gwaerzw2 ? 2 : 1,
                    'primaryHeating' => [
                        'heatGenerator' => $buildingMetadata->gwaerzh1,
                        'energySource' => $buildingMetadata->genh1,
                    ],
                ],
                'location' => [
                    'canton' => $buildingMetadata->gdekt,
                    'municipalityName' => $buildingMetadata->ggdename,
                    'coordinates' => [
                        'latitude' => $place->geo->latitude,
                        'longitude' => $place->geo->longitude,
                    ],
                ],
            ];

            $buildings[] = $building;
        }

        return new JsonResponse([
            'query' => $searchQuery,
            'count' => count($buildings),
            'buildings' => $buildings,
        ]);
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