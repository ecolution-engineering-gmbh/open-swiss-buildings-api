<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250627222929 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create building_address_mapping table to link buildings with their entrances/addresses';
    }

    public function up(Schema $schema): void
    {
        // Create building_address_mapping table
        $this->addSql('CREATE TABLE building_address_mapping (
            id UUID NOT NULL,
            egid VARCHAR(9) NOT NULL,
            building_entrance_id UUID NOT NULL,
            entrance_id VARCHAR(2) NOT NULL,
            is_primary_entrance BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY(id)
        )');
        
        // Add foreign key constraints
        $this->addSql('ALTER TABLE building_address_mapping ADD CONSTRAINT FK_building_metadata 
            FOREIGN KEY (egid) REFERENCES building_metadata (egid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE building_address_mapping ADD CONSTRAINT FK_building_entrance 
            FOREIGN KEY (building_entrance_id) REFERENCES building_entrance (id) ON DELETE CASCADE');
        
        // Add indexes for performance
        $this->addSql('CREATE INDEX idx_building_address_mapping_egid ON building_address_mapping (egid)');
        $this->addSql('CREATE INDEX idx_building_address_mapping_entrance ON building_address_mapping (building_entrance_id)');
        $this->addSql('CREATE INDEX idx_building_address_mapping_primary ON building_address_mapping (is_primary_entrance)');
        $this->addSql('CREATE UNIQUE INDEX idx_building_address_mapping_unique ON building_address_mapping (egid, building_entrance_id)');
        
        // UUID type comment
        $this->addSql('COMMENT ON COLUMN building_address_mapping.id IS \'(DC2Type:uuid)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE building_address_mapping');
    }
}