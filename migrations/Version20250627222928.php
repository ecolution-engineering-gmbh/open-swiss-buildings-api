<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250627222928 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create building_metadata table with comprehensive GWR federal building data';
    }

    public function up(Schema $schema): void
    {
        // Create building_metadata table with all GWR fields from federal database
        $this->addSql('CREATE TABLE building_metadata (
            egid VARCHAR(9) NOT NULL,
            gdekt VARCHAR(2) NOT NULL,
            ggdenr VARCHAR(4) NOT NULL,
            ggdename VARCHAR(40) NOT NULL,
            egrid VARCHAR(14) NOT NULL,
            lgbkr VARCHAR(4) NOT NULL,
            lparz VARCHAR(12) NOT NULL,
            lparzsx VARCHAR(12) NOT NULL,
            ltyp VARCHAR(4) NOT NULL,
            gebnr VARCHAR(12) NOT NULL,
            gbez VARCHAR(40) NOT NULL,
            gkode VARCHAR(11) NOT NULL,
            gkodn VARCHAR(11) NOT NULL,
            gksce VARCHAR(3) NOT NULL,
            gstat VARCHAR(4) NOT NULL,
            gkat VARCHAR(4) NOT NULL,
            gklas VARCHAR(4) NOT NULL,
            gbauj VARCHAR(4) NOT NULL,
            gbaum VARCHAR(2) NOT NULL,
            gbaup VARCHAR(4) NOT NULL,
            gabbj VARCHAR(4) NOT NULL,
            garea VARCHAR(5) NOT NULL,
            gvol VARCHAR(7) NOT NULL,
            gvolnorm VARCHAR(3) NOT NULL,
            gvolsce VARCHAR(3) NOT NULL,
            gastw VARCHAR(2) NOT NULL,
            ganzwhg VARCHAR(3) NOT NULL,
            gazzi VARCHAR(3) NOT NULL,
            gschutzr VARCHAR(1) NOT NULL,
            gebf VARCHAR(6) NOT NULL,
            gwaerzh1 VARCHAR(4) NOT NULL,
            genh1 VARCHAR(4) NOT NULL,
            gwaersceh1 VARCHAR(3) NOT NULL,
            gwaerdath1 DATE NOT NULL,
            gwaerzh2 VARCHAR(4) NOT NULL,
            genh2 VARCHAR(4) NOT NULL,
            gwaersceh2 VARCHAR(3) NOT NULL,
            gwaerdath2 DATE NOT NULL,
            gwaerzw1 VARCHAR(4) NOT NULL,
            genw1 VARCHAR(4) NOT NULL,
            gwaerscew1 VARCHAR(3) NOT NULL,
            gwaerdatw1 DATE NOT NULL,
            gwaerzw2 VARCHAR(4) NOT NULL,
            genw2 VARCHAR(4) NOT NULL,
            gwaerscew2 VARCHAR(3) NOT NULL,
            gwaerdatw2 DATE NOT NULL,
            gexpdat DATE NOT NULL,
            PRIMARY KEY(egid)
        )');
        
        // Add indexes for performance
        $this->addSql('CREATE INDEX idx_building_metadata_egrid ON building_metadata (egrid)');
        $this->addSql('CREATE INDEX idx_building_metadata_municipality ON building_metadata (ggdenr)');
        $this->addSql('CREATE INDEX idx_building_metadata_canton ON building_metadata (gdekt)');
        $this->addSql('CREATE INDEX idx_building_metadata_status ON building_metadata (gstat)');
        $this->addSql('CREATE INDEX idx_building_metadata_category ON building_metadata (gkat)');
        $this->addSql('CREATE INDEX idx_building_metadata_construction_year ON building_metadata (gbauj)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE building_metadata');
    }
}