# CLAUDE.md - Swiss Buildings API Assistant Guide

## 🎯 Project Purpose

This is a production-ready REST API for Swiss and Liechtenstein building registry data (GWR - Gebäude- und Wohnungsregister). It provides comprehensive building metadata access for over 1 million buildings, including construction details, energy systems, physical characteristics, and precise location data.

**Primary Use Case**: Building and address system for CRM applications requiring rich Swiss federal building data.

## 🏗️ Architecture Overview

### Core Components
- **Framework**: Symfony 7.2 (PHP 8.4)
- **Database**: PostgreSQL 16 with PostGIS extension
- **Search Engine**: Meilisearch v1.13 for address autocomplete
- **Container**: Docker with S6 process supervisor
- **Deployment**: Docker Swarm production environment

### Service Architecture (Docker Swarm)
1. **swiss-buildings_app** - Main API application (Nginx + PHP-FPM)
2. **swiss-buildings_database** - PostgreSQL with PostGIS
3. **swiss-buildings_meilisearch** - Search engine for addresses
4. **swiss-buildings_worker-monitor** - Automatic worker management

### Data Architecture
- **Federal SQLite DB**: Complete building metadata from government sources (~3.2M buildings)
- **PostgreSQL**: Processed building entrance data with spatial indexing
- **Meilisearch**: 250K+ indexed addresses for fast autocomplete
- **PostGIS**: Geographic operations and coordinate transformations

## 🚀 Common Commands

### Deployment
```bash
# Deploy to production (Docker Swarm)
./deploy-to-swarm.sh

# Check deployment status
docker service ls | grep swiss-buildings

# Test API health
./test-api.sh
```

### Monitoring & Maintenance
```bash
# View service logs
docker service logs swiss-buildings_app -f
docker service logs swiss-buildings_worker-monitor -f

# Check worker status
docker service ps swiss-buildings_app

# Manual worker restart (if needed)
./restart-workers.sh

# Force data refresh
docker exec $(docker ps --filter "name=swiss-buildings_app" -q) \
  php bin/console app:registry:ch:download

# Reindex search
docker exec $(docker ps --filter "name=swiss-buildings_app" -q) \
  php bin/console app:address-search:index-all
```

### CLI Data Access
```bash
# Get complete building metadata by EGID
docker exec $(docker ps --filter "name=swiss-buildings_app" -q) \
  php bin/console app:registry:ch:list --building-id=150404

# Search buildings by municipality
docker exec $(docker ps --filter "name=swiss-buildings_app" -q) \
  php bin/console app:registry:ch:list --municipality-name=Zürich --limit=10

# Filter by building status
docker exec $(docker ps --filter "name=swiss-buildings_app" -q) \
  php bin/console app:registry:ch:list --building-status=existing --limit=5
```

### Testing & Development
```bash
# No specific test commands found - project uses production deployment approach
# API testing via test-api.sh script
```

## 🏢 Building Metadata Capabilities

### Available Data Categories

**Construction Details:**
- Baujahr (Construction Year), Baumonat (Construction Month)
- Gebäudekategorie (Building Category), Gebäudeklasse (Building Class)
- Building status (existing, planned, demolished, under_construction, approved)

**Physical Characteristics:**
- Gebäudefläche (Building Area), Gebäudevolumen (Building Volume)
- Anzahl Geschosse (Number of Floors), Anzahl Wohnungen (Number of Apartments)
- Zivilschutzraum (Civil Defense Shelter), Separate Wohnräume (Separate Living Rooms)

**Energy Systems (up to 4 per building):**
- 2 Heating systems with heat generator types and energy sources
- 2 Hot water systems with separate tracking
- Energiebezugsfläche (Energy Reference Area)
- Last update dates for each system

**Location & Identification:**
- EGID (Federal Building Identifier), EGRID (Federal Land Identifier)
- Multiple addresses/entrances with precise coordinates
- LV95 (Swiss) and WGS84 (GPS) coordinate systems
- Canton and municipality information

## 📡 Key API Endpoints

### Building Metadata Endpoints
- `GET /buildings/egid/{egid}` - Get complete building by EGID
- `GET /buildings/egrid/{egrid}` - Get building by EGRID  
- `GET /buildings/address` - Search buildings by address with metadata

### Search & Discovery
- `GET /address-search/find` - Address autocomplete
- `GET /address-search/stats` - Search index statistics
- `GET /addresses` - List all addresses (paginated)

### Bulk Resolution (Async)
- `POST /resolve/building-ids` - Resolve EGIDs to building data
- `POST /resolve/address-search` - Resolve address text to buildings
- `POST /resolve/geo-json` - Resolve coordinates to buildings
- `POST /resolve/municipalities-codes` - Get all buildings in municipalities

### Job Management
- `GET /resolve/jobs/{id}` - Check async job status
- `GET /resolve/jobs/{id}/results` - Get job results

### System
- `GET /ping` - Health check
- `GET /doc` - Swagger UI documentation

## 🔧 Integration Patterns

### Internal Service Access
- **Service URL**: `http://swiss-buildings_app:80`
- **Network**: `swiss-buildings_default` (Docker overlay)
- **No external access** - internal Swarm network only

### Example API Usage
```bash
# Get building metadata
curl "http://swiss-buildings_app:80/buildings/egid/150404"

# Search by address
curl "http://swiss-buildings_app:80/buildings/address?adresse=Limmatstrasse%20112%20Zürich"

# Bulk resolution
curl -X POST -H "Content-Type: text/csv" \
  -d "egid\n150404\n150427" \
  http://swiss-buildings_app:80/resolve/building-ids
```

## 🚨 Important Deployment Context

### Docker Swarm Environment
- **Production Context**: `ecolution` (Infomaniak server)
- **Environment Variables**: Must be manually updated due to Swarm substitution issues
- **Auto-deployment**: `deploy-to-swarm.sh` handles all environment variable fixes

### Critical Settings
- `S6_BEHAVIOUR_IF_STAGE2_FAILS=0` - Prevents worker startup failures
- Worker-monitor service ensures automatic worker recovery
- Database credentials must match across all services

### Known Issues & Auto-Solutions
1. **Environment Variable Substitution**: Fixed by deployment script
2. **Worker Startup Timing**: Handled by worker-monitor service
3. **Container Class Loading**: Avoid adding new controllers - use existing endpoints

## 📂 Key Files & Directories

### Configuration & Deployment
- `compose.final.yaml` - Production Docker Swarm configuration
- `deploy-to-swarm.sh` - Automated deployment with env var fixes
- `.env.production` - Environment template
- `restart-workers.sh` - Manual worker restart utility

### Documentation (init/ folder)
- `API_ENDPOINTS.md` - Complete endpoint documentation
- `BUILDING_METADATA.md` - Comprehensive data field reference
- `PROJECT_OVERVIEW.md` - Technical architecture details

### Application Structure
- `src/` - Symfony application code
- `config/` - Symfony configuration
- `docker/` - Container configuration
- `migrations/` - Database schema migrations

## 🔄 Background Workers

### Worker Types & Functions
1. **Resolver Worker**: Processes bulk CSV/GeoJSON jobs with building metadata
2. **Async Worker**: Handles search indexing and general async tasks  
3. **Scheduler**: Weekly data refresh from government sources (Mondays)

### Worker Management
- Auto-restart every hour for stability
- Worker-monitor service handles automatic recovery
- Manual restart available via `./restart-workers.sh`

## 📊 Data Management

### Data Sources
- **Swiss Federal Statistical Office**: Weekly updates (Mondays 10:00 CET)
- **Liechtenstein Statistics**: Weekly updates (Mondays 08:30 CET)
- **Coverage**: Complete Switzerland + Liechtenstein building registry

### Data Persistence
- PostgreSQL volumes for processed data
- Meilisearch volumes for search indices
- Federal SQLite data downloaded automatically
- Weekly automatic refresh from government sources

## 🎯 User Intent & CRM Integration

This API was specifically deployed for CRM building and address system integration with requirements for:
- Fetching Swiss buildings by address with **complete building metadata**
- Direct lookup by EGID with full federal data
- Returning rich building characteristics for CRM data enrichment
- Access to construction details, energy systems, and physical properties

The emphasis is on **building metadata**, not just addresses - providing comprehensive federal data including Baujahr, Gebäudetyp, energy systems, and all physical characteristics from the official Swiss building registry.

## 📚 Documentation References

For detailed information, refer to:
- [README.md](README.md) - Complete project overview
- [init/API_ENDPOINTS.md](init/API_ENDPOINTS.md) - Detailed endpoint documentation  
- [init/BUILDING_METADATA.md](init/BUILDING_METADATA.md) - Complete data field reference
- [rust-api-integration.md](rust-api-integration.md) - Integration guide for external services