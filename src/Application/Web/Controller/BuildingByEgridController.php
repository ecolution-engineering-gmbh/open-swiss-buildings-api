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

final class BuildingByEgridController extends AbstractController
{
    public function __construct(
        private readonly BuildingMetadataRepository $buildingMetadataRepository,
        private readonly BuildingAddressMappingRepository $mappingRepository,
    ) {}

    /**
     * Get building metadata by EGRID (Federal Land Identifier)
     *
     * Returns the same comprehensive building data as the EGID endpoint,
     * but allows lookup by the federal property identifier (EGRID).
     */
    #[Route('/buildings/egrid/{egrid}', methods: ['GET'])]
    #[OA\Parameter(
        name: 'egrid',
        description: 'Federal Land Identifier (EGRID) - 14 character identifier',
        in: 'path',
        required: true,
        example: 'CH123456789012'
    )]
    #[OA\Response(
        response: '200',
        description: 'Returns complete building metadata with all addresses',
        content: new OA\JsonContent(ref: '#/components/schemas/BuildingMetadata')
    )]
    #[OA\Response(response: '404', description: 'Building not found')]
    #[OA\Tag(name: 'Building Metadata')]
    public function __invoke(string $egrid): Response
    {
        // Find building metadata by EGRID
        $buildingMetadata = $this->buildingMetadataRepository->findByEgrid($egrid);
        
        if (null === $buildingMetadata) {
            return new JsonResponse(['error' => 'Building not found'], Response::HTTP_NOT_FOUND);
        }

        // Redirect to EGID controller for the actual response building
        $egidController = new BuildingByEgidController(
            $this->buildingMetadataRepository,
            $this->mappingRepository
        );

        return $egidController($buildingMetadata->egid);
    }
}