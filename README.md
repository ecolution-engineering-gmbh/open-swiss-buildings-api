# Swiss Buildings API 🏢

Production-ready REST API for Swiss and Liechtenstein building registry data (GWR - Gebäude- und Wohnungsregister), providing comprehensive building metadata, address search, and bulk resolution services for CRM integration.

## 🚀 Features

- **3.1M+ Swiss Buildings**: Complete federal building registry (GWR) with rich metadata
- **Advanced Building Data**: Construction details, energy systems, physical characteristics, land registry information
- **Smart Address Linking**: UUID-based address-building relationships with multiple identifiers (EGID, EGRID, coordinates)
- **Address Autocomplete**: Fast search across 250K+ indexed addresses with geographic precision
- **Bulk Resolution**: Process CSV files with building IDs, addresses, or coordinates
- **Cadastral Integration**: Land registry district numbers, property identifiers, and coordinate systems (LV95/WGS84)
- **Weekly Updates**: Automatic data refresh from government sources
- **Docker Swarm Ready**: Built for production deployment with auto-scaling
- **Worker Management**: Automatic background job processing

## 📊 Data Sources

- 🇨🇭 [Swiss Federal Statistical Office (FSO)](https://www.housing-stat.ch/de/madd/public.html) - Updated **weekly** (Mondays 10:00)
- 🇱🇮 [Statistikportal Liechtenstein](https://www.statistikportal.li/de/zahlen-fakten/epublikationen?category=15&order=date) - Updated **weekly** (Mondays 08:30)

## 🛠️ Quick Start

### Prerequisites
- Docker Swarm cluster
- Production server access via Docker context

### 1. Clone and Configure
```bash
git clone https://github.com/liip/open-swiss-buildings-api.git
cd open-swiss-buildings-api

# Copy environment template
cp .env.production .env

# Edit .env with your credentials (passwords, API keys)
nano .env
```

### 2. Deploy to Production
```bash
# Ensure you're connected to production
docker context use ecolution  # or your production context

# Deploy everything automatically
./deploy-to-swarm.sh

# Check deployment status
docker service ls | grep swiss-buildings
```

### 3. Verify Installation
```bash
# Test API health
./test-api.sh

# Or manually check endpoints
curl http://swiss-buildings_app:80/ping
curl http://swiss-buildings_app:80/address-search/stats
```

## 📡 API Endpoints

### Building Metadata Endpoints 🏢
- `GET /buildings/egid/{egid}` - **Complete building by EGID** with all metadata
- `GET /buildings/egrid/{egrid}` - Building by land registry property identifier  
- `GET /buildings/address?adresse={text}` - Search buildings by address with full metadata
- `GET /buildings/stats` - Building registry statistics and coverage info

### Address-Building Integration 🔗
- `GET /addresses` - **List all addresses (paginated)** with building links
- `GET /addresses/{uuid}` - Specific address details (schema.org format)
- `GET /addresses/{uuid}/building` - **Address with complete building metadata**
- `GET /address-search/find?query={text}` - **Autocomplete with building IDs**
- `GET /address-search/stats` - Search index statistics

### Bulk Resolution (Async) 📊
- `POST /resolve/building-ids` - **Resolve EGIDs to complete building data**
- `POST /resolve/address-search` - Resolve address text to buildings
- `POST /resolve/geo-json` - **Resolve coordinates to buildings with metadata**
- `POST /resolve/municipalities-codes` - Get all buildings in municipalities

### Job Management 🔄
- `GET /resolve/jobs/{id}` - Check async job status
- `GET /resolve/jobs/{id}/results` - Get job results with metadata

### System 🔧
- `GET /ping` - Health check
- `GET /doc` - **Swagger UI documentation** with examples

## 🔧 Example Usage

### Building Metadata Access
```bash
# Get complete building data by EGID (Federal Building ID)
curl "http://swiss-buildings_app:80/buildings/egid/150404"

# Get building by EGRID (Property ID) 
curl "http://swiss-buildings_app:80/buildings/egrid/CH807306258641"

# Search buildings by address text
curl "http://swiss-buildings_app:80/buildings/address?adresse=Limmatstrasse%20112%20Zürich"
```

### Address-Building Integration
```bash
# Find addresses with building links
curl "http://swiss-buildings_app:80/address-search/find?query=Limmatstrasse&limit=5"

# Get specific address with building metadata
curl "http://swiss-buildings_app:80/addresses/0197b2d1-8bdc-70e5-9831-ea4f09b6baed/building"

# List addresses (paginated) - contains buildingId for linking
curl "http://swiss-buildings_app:80/addresses?limit=10&offset=0"
```

### Bulk Resolution with Metadata
```bash
# Submit CSV with building IDs - returns complete building metadata
curl -X POST -H "Content-Type: text/csv" \
  -d "egid
150404
150427" \
  http://swiss-buildings_app:80/resolve/building-ids

# Resolve coordinates to buildings with metadata
curl -X POST -H "Content-Type: application/geo+json" \
  -d '{
    "type": "FeatureCollection",
    "features": [{
      "type": "Feature",
      "geometry": {"type": "Point", "coordinates": [8.541694, 47.366424]}
    }]
  }' \
  http://swiss-buildings_app:80/resolve/geo-json

# Returns job ID, then poll for results with complete building data
curl http://swiss-buildings_app:80/resolve/jobs/{job-id}
curl http://swiss-buildings_app:80/resolve/jobs/{job-id}/results
```

## 🔗 Address-Building Relationship & Data Structure

### **Smart Linking Mechanisms** 
The API provides multiple ways to connect addresses with buildings using Swiss federal standards:

#### **Building Identifiers**
- **EGID** (`egid`): 9-digit Federal Building Identifier - **primary key**
- **EGRID** (`egrid`): 14-character Federal Property Identifier 
- **GEBNR** (`gebnr`): Official Building Number
- **Building Name** (`gbez`): Optional building designation

#### **Address Identifiers**
- **Address UUID**: UuidV7 primary key for each entrance (`0197b2d1-8bdc-70e5-9831-ea4f09b6baed`)
- **Address ID** (`addressId`): 9-digit federal address identifier
- **Building ID** (`buildingId`): Links address to building EGID in response JSON

#### **Coordinate Systems & Spatial Data**
- **LV95 (Swiss National Grid)**: High-precision Swiss coordinates in building metadata
- **WGS84 (GPS)**: Latitude/longitude coordinates for addresses
- **PostGIS Integration**: Spatial queries and geographic operations
- **Coordinate Quality**: Quality indicators for precision assessment

#### **Cadastral & Land Registry Data**
- **LGBKR**: Land Registry District Number (4 digits)
- **LPARZ**: Property/Plot Number (12 characters)
- **EGRID**: Federal Property Identifier linking buildings to land
- **Administrative Boundaries**: Canton codes, municipality identifiers

### **Amazing Connection Strategies**

#### **1. Address → Building Metadata**
```bash
# Step 1: Find address UUID
curl "http://swiss-buildings_app:80/address-search/find?query=Limmatstrasse+112"
# Returns: {"buildingId": "150404", "identifier": "uuid-here"}

# Step 2: Get complete building data  
curl "http://swiss-buildings_app:80/buildings/egid/150404"
# Returns: Complete GWR building metadata with construction, energy, cadastral data
```

#### **2. Building → All Addresses/Entrances**
```bash
# Get building with all linked addresses
curl "http://swiss-buildings_app:80/buildings/egid/150404"
# Returns: Building metadata + array of all entrance addresses
```

#### **3. Coordinate → Building + Address**
```bash
# Submit GPS coordinates via GeoJSON
curl -X POST -H "Content-Type: application/geo+json" \
  -d '{"type":"Feature","geometry":{"type":"Point","coordinates":[8.541,47.366]}}' \
  http://swiss-buildings_app:80/resolve/geo-json
# Returns: Nearest building with complete metadata + entrance address
```

#### **4. Bulk Address-Building Resolution**
```bash
# Submit CSV with mixed identifiers
curl -X POST -H "Content-Type: text/csv" \
  -d "query
Limmatstrasse 112 Zürich
EGID:150404  
8.541694,47.366424" \
  http://swiss-buildings_app:80/resolve/address-search
# Returns: Unified results with building metadata for all query types
```

### **Building Metadata Categories**

#### **🏗️ Construction Details**
- Construction year (`gbauj`), month (`gbaum`), period (`gbaup`)
- Building category (`gkat`), class (`gklas`), status (`gstat`)
- Number of floors (`gastw`), apartments (`ganzwhg`)
- Building area (`garea`), volume (`gvol`)

#### **⚡ Energy Systems** (Up to 4 systems per building)
- 2x Heating systems with generator types and energy sources
- 2x Hot water systems with separate tracking
- Energy reference area (`gebf`)
- System installation dates and last updates

#### **📍 Location & Cadastral**
- LV95 coordinates (`gkode`, `gkodn`) with quality indicators
- Land registry district (`lgbkr`), property number (`lparz`)
- Canton (`gdekt`), municipality codes (`ggdenr`)
- EGRID property identifier for land registry integration

## 🏗️ Architecture

### Services
1. **swiss-buildings_app** - API application (Nginx + PHP-FPM)
2. **swiss-buildings_database** - PostgreSQL with PostGIS
3. **swiss-buildings_meilisearch** - Search engine  
4. **swiss-buildings_worker-monitor** - Automatic worker management

### Background Workers
- **Resolver**: Processes bulk CSV/GeoJSON jobs
- **Async**: Handles search indexing and maintenance
- **Scheduler**: Weekly data updates from registries

## 🔄 Maintenance

### Monitoring
```bash
# View service logs
docker service logs swiss-buildings_app -f
docker service logs swiss-buildings_worker-monitor -f

# Check worker status
docker service ps swiss-buildings_app
```

### Manual Operations
```bash
# Restart workers if needed
./restart-workers.sh

# Force data refresh
docker exec $(docker ps --filter "name=swiss-buildings_app" -q) \
  php bin/console app:registry:ch:download

# Reindex search
docker exec $(docker ps --filter "name=swiss-buildings_app" -q) \
  php bin/console app:address-search:index-all
```

## 📚 Documentation

- [API Integration Guide](rust-api-integration.md) - For Rust/external service integration
- [API Endpoints Reference](init/API_ENDPOINTS.md) - Detailed endpoint documentation
- [Troubleshooting Guide](init/TROUBLESHOOTING.md) - Common issues and solutions
- [Project Overview](init/PROJECT_OVERVIEW.md) - Technical architecture details

## 🤝 Integration

### For Rust Services
```rust
let api_url = "http://swiss-buildings_app:80";

// Example: Search addresses
let response = client
    .get(format!("{}/address-search/find", api_url))
    .query(&[("query", "Basel"), ("limit", "10")])
    .send()
    .await?;
```

### Docker Network
Services can connect via:
- Service name: `swiss-buildings_app`
- Network: `swiss-buildings_default`
- Port: 80

## ⚙️ Configuration

### Environment Variables
See `.env.production` for required variables:
- `APP_SECRET` - Symfony application secret
- `POSTGRES_PASSWORD` - Database password
- `MEILI_MASTER_KEY` - Meilisearch API key

### Data Persistence
- Database: `swiss-buildings_database_data` volume
- Search: `swiss-buildings_meilisearch_data` volume
- No backup needed - data refreshes weekly from source

## 🚨 Production Notes

1. **Internal Only**: No external access configured
2. **Auto-Recovery**: Workers restart automatically
3. **Scaling**: Can scale app service, workers handle all instances
4. **Updates**: Use `docker stack deploy` for zero-downtime updates
5. **Resource Usage**: ~500MB RAM idle, up to 1GB during imports

## 📝 License

This project is licensed under the MIT License. See LICENSE file for details.

## 🙏 Acknowledgments

- [Swiss Federal Statistical Office](https://www.bfs.admin.ch/) for GWR data
- [Principality of Liechtenstein](https://www.statistikportal.li/) for building registry
- Built with [Symfony](https://symfony.com/), [Meilisearch](https://www.meilisearch.com/), and [PostGIS](https://postgis.net/)