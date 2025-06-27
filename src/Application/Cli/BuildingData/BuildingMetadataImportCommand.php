<?php

declare(strict_types=1);

namespace App\Application\Cli\BuildingData;

use App\Domain\BuildingData\Entity\BuildingAddressMapping;
use App\Domain\BuildingData\Entity\BuildingMetadata;
use App\Domain\BuildingData\Repository\BuildingAddressMappingRepository;
use App\Domain\BuildingData\Repository\BuildingEntranceRepository;
use App\Domain\BuildingData\Repository\BuildingMetadataRepository;
use App\Domain\Registry\DataCH\Repository\BuildingRepository as RegistryBuildingRepository;
use App\Domain\Registry\DataCH\Repository\EntranceRepository as RegistryEntranceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:building-metadata:import',
    description: 'Import building metadata from GWR SQLite database to PostgreSQL and create address mappings'
)]
class BuildingMetadataImportCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RegistryBuildingRepository $registryBuildingRepository,
        private readonly RegistryEntranceRepository $registryEntranceRepository,
        private readonly BuildingMetadataRepository $buildingMetadataRepository,
        private readonly BuildingEntranceRepository $buildingEntranceRepository,
        private readonly BuildingAddressMappingRepository $mappingRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Batch size for processing', 1000)
            ->addOption('clear-existing', null, InputOption::VALUE_NONE, 'Clear existing building metadata before import')
            ->addOption('skip-mappings', null, InputOption::VALUE_NONE, 'Skip creating address mappings')
            ->setHelp('This command imports building metadata from the GWR SQLite database and creates mappings to addresses');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSize = (int) $input->getOption('batch-size');
        $clearExisting = $input->getOption('clear-existing');
        $skipMappings = $input->getOption('skip-mappings');

        $io->title('Building Metadata Import');

        // Clear existing data if requested
        if ($clearExisting) {
            $io->section('Clearing existing building metadata...');
            $this->clearExistingData();
            $io->success('Existing data cleared');
        }

        // Import building metadata
        $io->section('Importing building metadata from GWR SQLite database...');
        $metadataCount = $this->importBuildingMetadata($io, $batchSize);
        $io->success(sprintf('Imported %d building metadata records', $metadataCount));

        // Create address mappings
        if (!$skipMappings) {
            $io->section('Creating building-address mappings...');
            $mappingCount = $this->createAddressMappings($io, $batchSize);
            $io->success(sprintf('Created %d building-address mappings', $mappingCount));
        }

        $io->success('Building metadata import completed successfully!');

        return Command::SUCCESS;
    }

    private function importBuildingMetadata(SymfonyStyle $io, int $batchSize): int
    {
        // Get total count for progress bar
        $totalBuildings = $this->registryBuildingRepository->countActiveBuildings();
        $io->writeln(sprintf('Found %d active buildings in registry', $totalBuildings));

        $progressBar = new ProgressBar($io, $totalBuildings);
        $progressBar->start();

        $processedCount = 0;
        $offset = 0;

        while ($offset < $totalBuildings) {
            // Fetch batch of buildings from SQLite
            $buildings = $this->registryBuildingRepository->findActiveBuildings($batchSize, $offset);
            
            $batch = [];
            foreach ($buildings as $building) {
                // Create BuildingMetadata entity
                $metadata = new BuildingMetadata();
                $metadata->egid = $building->EGID;
                $metadata->gdekt = $building->GDEKT;
                $metadata->ggdenr = $building->GGDENR;
                $metadata->ggdename = $building->GGDENAME;
                $metadata->egrid = $building->EGRID;
                $metadata->lgbkr = $building->LGBKR;
                $metadata->lparz = $building->LPARZ;
                $metadata->lparzsx = $building->LPARZSX;
                $metadata->ltyp = $building->LTYP;
                $metadata->gebnr = $building->GEBNR;
                $metadata->gbez = $building->GBEZ;
                $metadata->gkode = $building->GKODE;
                $metadata->gkodn = $building->GKODN;
                $metadata->gksce = $building->GKSCE;
                $metadata->gstat = $building->GSTAT->value;
                $metadata->gkat = $building->GKAT;
                $metadata->gklas = $building->GKLAS;
                $metadata->gbauj = $building->GBAUJ;
                $metadata->gbaum = $building->GBAUM;
                $metadata->gbaup = $building->GBAUP;
                $metadata->gabbj = $building->GABBJ;
                $metadata->garea = $building->GAREA;
                $metadata->gvol = $building->GVOL;
                $metadata->gvolnorm = $building->GVOLNORM;
                $metadata->gvolsce = $building->GVOLSCE;
                $metadata->gastw = $building->GASTW;
                $metadata->ganzwhg = $building->GANZWHG;
                $metadata->gazzi = $building->GAZZI;
                $metadata->gschutzr = $building->GSCHUTZR;
                $metadata->gebf = $building->GEBF;
                $metadata->gwaerzh1 = $building->GWAERZH1;
                $metadata->genh1 = $building->GENH1;
                $metadata->gwaersceh1 = $building->GWAERSCEH1;
                $metadata->gwaerdath1 = $building->GWAERDATH1;
                $metadata->gwaerzh2 = $building->GWAERZH2;
                $metadata->genh2 = $building->GENH2;
                $metadata->gwaersceh2 = $building->GWAERSCEH2;
                $metadata->gwaerdath2 = $building->GWAERDATH2;
                $metadata->gwaerzw1 = $building->GWAERZW1;
                $metadata->genw1 = $building->GENW1;
                $metadata->gwaerscew1 = $building->GWAERSCEW1;
                $metadata->gwaerdatw1 = $building->GWAERDATW1;
                $metadata->gwaerzw2 = $building->GWAERZW2;
                $metadata->genw2 = $building->GENW2;
                $metadata->gwaerscew2 = $building->GWAERSCEW2;
                $metadata->gwaerdatw2 = $building->GWAERDATW2;
                $metadata->gexpdat = $building->GEXPDAT;

                $batch[] = $metadata;
            }

            // Batch insert to PostgreSQL
            if (!empty($batch)) {
                foreach ($batch as $metadata) {
                    $this->entityManager->persist($metadata);
                }
                $this->entityManager->flush();
                $this->entityManager->clear();
            }

            $processedCount += count($batch);
            $progressBar->advance(count($batch));
            $offset += $batchSize;
        }

        $progressBar->finish();
        $io->newLine();

        return $processedCount;
    }

    private function createAddressMappings(SymfonyStyle $io, int $batchSize): int
    {
        // Get all building entrances that need mapping
        $totalEntrances = $this->buildingEntranceRepository->countAll();
        $io->writeln(sprintf('Found %d building entrances to map', $totalEntrances));

        $progressBar = new ProgressBar($io, $totalEntrances);
        $progressBar->start();

        $mappingCount = 0;
        $offset = 0;

        while ($offset < $totalEntrances) {
            // Get batch of entrances
            $entrances = $this->buildingEntranceRepository->findBatch($batchSize, $offset);

            foreach ($entrances as $entrance) {
                // Check if mapping already exists
                if (!$this->mappingRepository->mappingExists($entrance->getBuildingId(), $entrance->getId())) {
                    // Create new mapping
                    $mapping = new BuildingAddressMapping();
                    $mapping->egid = $entrance->getBuildingId();
                    $mapping->buildingEntranceId = $entrance->getId();
                    $mapping->entranceId = $entrance->getEntranceId();
                    
                    // Set as primary if it's entrance "0" (main entrance)
                    $mapping->isPrimaryEntrance = ($entrance->getEntranceId() === '0');

                    $this->entityManager->persist($mapping);
                    $mappingCount++;
                }
            }

            // Batch flush
            $this->entityManager->flush();
            $this->entityManager->clear();

            $progressBar->advance(count($entrances));
            $offset += $batchSize;
        }

        $progressBar->finish();
        $io->newLine();

        return $mappingCount;
    }

    private function clearExistingData(): void
    {
        // Clear mappings first (due to foreign key constraints)
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE building_address_mapping CASCADE');
        
        // Clear building metadata
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE building_metadata CASCADE');
        
        $this->entityManager->flush();
    }
}