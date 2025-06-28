# Swiss Buildings API - Production Integration Guide

## Overview

The Swiss Buildings API provides access to **3.1M+ Swiss building records** with complete federal metadata and **250K+ searchable addresses** from the official GWR (Gebäude- und Wohnungsregister) registry. This production-grade internal service runs on your Ecolution Docker Swarm cluster with advanced building-address linking capabilities.

## Deployment Status

**Current Status**: ✅ Production Ready on Ecolution with Enhanced Building Metadata
- ✅ **Database**: PostgreSQL with PostGIS (3.1M+ buildings with complete metadata)
- ✅ **Building Data**: Complete GWR federal registry with construction, energy, cadastral data
- ✅ **Address Linking**: Smart UUID-based address-building relationships
- ✅ **Search**: Meilisearch (250K+ indexed addresses with building links)  
- ✅ **API**: Web server with enhanced building metadata endpoints
- ✅ **Workers**: Background job processing for bulk operations
- ✅ **Auto-refresh**: Weekly GWR data updates with complete metadata import

```bash
# Check deployment status:
docker context use ecolution  # Switch to production context
docker service ls | grep swiss-buildings

# Expected output:
# swiss-buildings_app           replicated   1/1    
# swiss-buildings_database      replicated   1/1    
# swiss-buildings_meilisearch   replicated   1/1    

# View logs:
docker service logs swiss-buildings_app --tail 50
```

## Docker Context Setup

### Production Environment Connection
```bash
# Your existing production context (already configured)
docker context use ecolution

# Verify connection to Swarm
docker node ls

# Deploy/update services
docker stack deploy -c compose.final.yaml swiss-buildings
```

## Connecting from Your Rust API

### Direct Service Connection (Recommended)
```rust
// Connect using actual Docker Swarm service name
let api_url = "http://swiss-buildings_app:80";

// Service is named: swiss-buildings_app (not swiss-buildings_swiss-buildings-api)
```

### Network Integration Options

#### Option 1: Same Docker Stack (Recommended)
Deploy your Rust API in the same stack to share the network automatically:

```yaml
# In your docker-compose:
version: '3.8'
services:
  rust-api:
    image: your-rust-api:latest
    networks:
      - default  # Shares network with swiss-buildings services
    # ... your config

  # swiss-buildings services are already in this network
networks:
  default:
    driver: overlay
    attachable: true
```

#### Option 2: Connect to External Network
```yaml
# In your Rust API docker-compose:
services:
  rust-api:
    image: your-rust-api:latest
    networks:
      - swiss-buildings_default  # Connect to existing network
      - your-other-networks

networks:
  swiss-buildings_default:
    external: true  # Use existing network
```

#### Option 3: Service Discovery (Current Setup)
```rust
// Direct service name resolution (works across all Swarm networks)
let api_url = "http://swiss-buildings_app:80";
```

## Complete API Endpoints Overview

### 🏢 **Building Metadata Endpoints** (Enhanced)

#### 1. Get Complete Building by EGID
```rust
// GET /buildings/egid/{egid} - Complete building metadata
let egid = "150404";
let building = client
    .get(format!("{}/buildings/egid/{}", api_url, egid))
    .send()
    .await?
    .json::<BuildingMetadata>()
    .await?;

// Returns: Complete GWR building data with construction, energy, cadastral info
```

#### 2. Get Building by Property ID (EGRID)
```rust
// GET /buildings/egrid/{egrid} - Building by land registry property ID
let egrid = "CH807306258641";
let building = client
    .get(format!("{}/buildings/egrid/{}", api_url, egrid))
    .send()
    .await?;

// Returns: Building linked to specific property/land parcel
```

#### 3. Search Buildings by Address with Metadata
```rust
// GET /buildings/address?adresse={text} - Address search with building metadata
let buildings = client
    .get(format!("{}/buildings/address", api_url))
    .query(&[("adresse", "Limmatstrasse 112 Zürich"), ("limit", "5")])
    .send()
    .await?
    .json::<BuildingsResponse>()
    .await?;

// Returns: [{"egid": "150404", "construction": {...}, "energySystems": {...}}]
```

### 🔍 **Search & Discovery**

#### 4. Address Autocomplete with Building Links
```rust
// GET /address-search/find?query={text}&limit={n}
let response = client
    .get(format!("{}/address-search/find", api_url))
    .query(&[("query", "Basel"), ("limit", "10")])
    .send()
    .await?;

// Returns: {"hits": [{"score": 96, "place": {...}, "buildingId": "150404"}]}
```

#### 5. Search Index Statistics
```rust
// GET /address-search/stats
let stats = client
    .get(format!("{}/address-search/stats", api_url))
    .send()
    .await?;

// Returns: {"status": "ok", "indexedAddresses": 250000}
```

### 📍 **Address-Building Integration**

#### 6. List All Addresses with Building Links
```rust
// GET /addresses?limit={n}&offset={n}
let response = client
    .get(format!("{}/addresses", api_url))
    .query(&[("limit", "100"), ("offset", "0")])
    .send()
    .await?
    .json::<AddressListResponse>()
    .await?;

// Returns: {"total": 250000, "results": [{"buildingId": "150404", ...}]}
```

#### 7. Get Address with Building Metadata
```rust
// GET /addresses/{uuid}/building - Address with complete building data
let address_uuid = "0197b276-a609-7b1d-8c68-42248c5a6717";
let address_with_building = client
    .get(format!("{}/addresses/{}/building", api_url, address_uuid))
    .send()
    .await?
    .json::<AddressWithBuilding>()
    .await?;

// Returns: Address details + complete building metadata
```

#### 8. Get Specific Address Details
```rust
// GET /addresses/{uuid} - Address details (schema.org format)
let address = client
    .get(format!("{}/addresses/{}", api_url, address_uuid))
    .send()
    .await?
    .json::<SchemaOrgAddress>()
    .await?;

// Returns: Full address with coordinates and buildingId link
```

### 🔧 **Bulk Resolution Services** (Async Jobs with Building Metadata)

#### 9. Resolve Building IDs to Complete Metadata
```rust
// POST /resolve/building-ids - Returns complete building metadata
let csv_data = "egid\n150404\n150427";
let job = client
    .post(format!("{}/resolve/building-ids", api_url))
    .header("Content-Type", "text/csv")
    .body(csv_data)
    .send()
    .await?
    .json::<JobInfo>()
    .await?;

// Results include: construction details, energy systems, cadastral data
```

#### 10. Resolve Address Text to Buildings
```rust
// POST /resolve/address-search - Address text to building metadata
let csv_data = "address\nLimmatstrasse 112 Zürich\nBaselstrasse 5 Bern";
let job = client
    .post(format!("{}/resolve/address-search", api_url))
    .header("Content-Type", "text/csv")
    .body(csv_data)
    .send()
    .await?
    .json::<JobInfo>()
    .await?;

// Returns: Matched buildings with complete federal metadata
```

#### 11. Resolve Coordinates to Buildings with Metadata
```rust
// POST /resolve/geo-json - Coordinates to nearest buildings
let geojson = r#"{
  "type": "FeatureCollection",
  "features": [{
    "type": "Feature",
    "geometry": {"type": "Point", "coordinates": [8.541694, 47.366424]}
  }]
}"#;
let job = client
    .post(format!("{}/resolve/geo-json", api_url))
    .header("Content-Type", "application/geo+json")
    .body(geojson)
    .send()
    .await?
    .json::<JobInfo>()
    .await?;

// Returns: Nearest buildings with complete metadata + distance
```

#### 12. Resolve Municipality Codes to All Buildings
```rust
// POST /resolve/municipalities-codes - All buildings in municipalities
let csv_data = "municipality_code\n261\n1061";
let job = client
    .post(format!("{}/resolve/municipalities-codes", api_url))
    .header("Content-Type", "text/csv")
    .body(csv_data)
    .send()
    .await?
    .json::<JobInfo>()
    .await?;

// Returns: All buildings in specified municipalities with metadata
```

### ⏱️ **Job Management**

#### 13. Check Job Status
```rust
// GET /resolve/jobs/{id}
let status = client
    .get(format!("{}/resolve/jobs/{}", api_url, job_id))
    .send()
    .await?
    .json::<JobStatus>()
    .await?;

// Returns: {"id": "...", "state": "completed|processing|failed", ...}
```

#### 14. Get Job Results with Building Metadata
```rust
// GET /resolve/jobs/{id}/results
let results = client
    .get(format!("{}/resolve/jobs/{}/results", api_url, job_id))
    .send()
    .await?
    .json::<JobResults>()
    .await?;

// Returns: Complete building metadata for all resolved items
// Example: {"results": [{"egid": "150404", "construction": {...}, "energySystems": {...}}]}
```

### 🏥 **Health & Documentation**

#### 15. Health Check
```rust
// GET /ping
let health = client
    .get(format!("{}/ping", api_url))
    .send()
    .await?;

// Returns: 204 No Content (success)
```

#### 16. API Documentation
```bash
# OpenAPI/Swagger UI: GET /doc - Complete documentation with building metadata examples
# OpenAPI Spec JSON: GET /doc.json
curl http://swiss-buildings_app:80/doc
```

## Current Data Status

- **🏢 Buildings**: **3.1M+ Swiss building entries** with complete federal metadata
- **📊 Building Data**: Construction details, energy systems, physical characteristics, cadastral information
- **📍 Addresses**: **250K+ searchable addresses** with building links
- **🔗 Address-Building Links**: UUID-based relationships with EGID, EGRID, coordinates
- **🔄 Updates**: Automatic weekly refresh (Mondays) from government sources
- **⚡ Performance**: Instant search, async bulk processing with complete metadata

## Async Job Pattern

All resolver endpoints (`/resolve/*`) use async jobs:

```rust
// 1. Submit job
let job = submit_resolver_job(data).await?;

// 2. Poll status
loop {
    let status = get_job_status(&job.id).await?;
    match status.state.as_str() {
        "completed" => break,
        "failed" => return Err("Job failed"),
        _ => tokio::time::sleep(Duration::from_secs(1)).await,
    }
}

// 3. Get results
let results = get_job_results(&job.id).await?;
```

## Service Discovery

- **swiss-buildings_app**: Main API service (port 80)
- **swiss-buildings_database**: PostgreSQL with PostGIS (internal only)
- **swiss-buildings_meilisearch**: Search engine (internal only)

## Production Setup & Management

### Deployment Commands
```bash
# Switch to production context
docker context use ecolution

# Deploy initial stack (includes automatic worker management)
./deploy-to-swarm.sh

# Update existing deployment
docker stack deploy -c compose.final.yaml swiss-buildings

# Check service health (now includes worker-monitor)
docker service ls | grep swiss-buildings
docker service logs swiss-buildings_app --tail 20
docker service logs swiss-buildings_worker-monitor --tail 20

# Manual worker restart (if needed)
./restart-workers.sh

# Scale services if needed
docker service scale swiss-buildings_app=2
```

### Connecting Your Rust API to Production

#### Development Testing
```bash
# Test from your local machine (with ecolution context active)
docker exec $(docker ps --filter "name=swiss-buildings_app" -q) \
  curl -s "http://localhost/address-search/stats"

# Expected: {"status": "ok", "indexedAddresses": 171000}
```

#### Production Deployment
```yaml
# Add to your existing Rust API stack
version: '3.8'
services:
  your-rust-api:
    image: your-rust-api:latest
    environment:
      - SWISS_BUILDINGS_URL=http://swiss-buildings_app:80
    networks:
      - swiss-buildings_default
    deploy:
      replicas: 1
      
networks:
  swiss-buildings_default:
    external: true
```

### Health Monitoring & Maintenance

#### Health Checks
```bash
# API health
curl -s http://swiss-buildings_app:80/ping  # Should return 204

# Data status  
curl -s http://swiss-buildings_app:80/address-search/stats

# Worker status
docker service ps swiss-buildings_app
```

#### Troubleshooting
```bash
# View detailed logs
docker service logs swiss-buildings_app -f

# Check worker processes
docker exec $(docker ps --filter "name=swiss-buildings_app" -q) ps aux | grep messenger

# Restart services if needed
docker service update --force swiss-buildings_app
```

#### Data Management
- **Auto-refresh**: GWR data updates every Monday (no action needed)
- **Manual refresh**: `docker exec ... php bin/console app:registry:ch:download`
- **Search reindex**: `docker exec ... php bin/console app:address-search:index-all`

## Automatic Worker Management

### 🤖 Worker Monitor Service
The deployment includes a `worker-monitor` service that:
- **Auto-detects** API readiness after container restarts
- **Automatically starts** workers once database is available
- **Restarts workers** every hour for stability
- **Monitors** worker health and functionality

### 🔄 Container Restart Behavior
When containers restart (due to updates, failures, etc.):
1. **API service** restarts and becomes ready
2. **Worker monitor** detects API availability  
3. **Workers automatically start** within 2-3 minutes
4. **Full functionality restored** without manual intervention

### 📊 Monitoring Commands
```bash
# Check all services (should show 4 services)
docker service ls | grep swiss-buildings

# Expected output:
# swiss-buildings_app              1/1
# swiss-buildings_database         1/1  
# swiss-buildings_meilisearch      1/1
# swiss-buildings_worker-monitor   1/1

# Monitor worker startup process
docker service logs swiss-buildings_worker-monitor -f

# Test functionality after restart
curl -X POST -H "Content-Type: text/csv" -d "egid\n123456" \
  http://swiss-buildings_app:80/resolve/building-ids
```

## Production Notes

1. **Internal Only**: No external access, Swarm network only
2. **Auto-recovery**: Workers auto-restart on container restarts
3. **Data Backup**: Not needed - weekly refresh from government sources  
4. **Health Monitoring**: Use `/ping` endpoint (returns 204)
5. **Resource Usage**: ~500MB RAM, minimal CPU when idle
6. **Network**: Uses overlay network `swiss-buildings_default`
7. **Scaling**: Can scale `app` service, worker-monitor handles all instances

## 🔗 Amazing Address-Building Connection Strategies

### **Smart Linking Methods**

#### **1. Address → Complete Building Metadata**
```rust
// Method 1: Direct address search with building data
pub async fn get_building_by_address(&self, address: &str) -> Result<Vec<BuildingWithMetadata>> {
    let buildings = self.client
        .get(&format!("{}/buildings/address", self.base_url))
        .query(&[("adresse", address), ("limit", "5")])
        .send()
        .await?
        .json::<BuildingsResponse>()
        .await?;
    
    Ok(buildings.buildings)
}

// Method 2: Two-step process for maximum control
pub async fn address_to_building_detailed(&self, address: &str) -> Result<BuildingMetadata> {
    // Step 1: Find address with building ID
    let search_results = self.search_addresses(address, 1).await?;
    let building_id = search_results.hits[0].place.additional_property.building_id;
    
    // Step 2: Get complete building metadata
    let building = self.get_building_by_egid(&building_id).await?;
    Ok(building)
}
```

#### **2. Building → All Addresses/Entrances**
```rust
pub async fn get_building_with_addresses(&self, egid: &str) -> Result<BuildingWithAddresses> {
    let building = self.client
        .get(&format!("{}/buildings/egid/{}", self.base_url, egid))
        .send()
        .await?
        .json::<BuildingWithAddresses>()
        .await?;
    
    // Building includes all entrance addresses in the response
    Ok(building)
}
```

#### **3. Coordinates → Buildings with Cadastral Data**
```rust
pub async fn resolve_coordinates_to_buildings(&self, lat: f64, lon: f64) -> Result<JobInfo> {
    let geojson = serde_json::json!({
        "type": "FeatureCollection",
        "features": [{
            "type": "Feature",
            "geometry": {
                "type": "Point", 
                "coordinates": [lon, lat]
            }
        }]
    });
    
    let job = self.client
        .post(&format!("{}/resolve/geo-json", self.base_url))
        .header("Content-Type", "application/geo+json")
        .json(&geojson)
        .send()
        .await?
        .json::<JobInfo>()
        .await?;
    
    Ok(job)
}
```

#### **4. UUID-Based Address Linking**
```rust
pub async fn get_address_with_building(&self, address_uuid: &str) -> Result<AddressWithBuilding> {
    let address_with_building = self.client
        .get(&format!("{}/addresses/{}/building", self.base_url, address_uuid))
        .send()
        .await?
        .json::<AddressWithBuilding>()
        .await?;
    
    Ok(address_with_building)
}
```

## Complete Enhanced Rust Client

```rust
use serde::{Deserialize, Serialize};
use reqwest::Client;
use std::collections::HashMap;

#[derive(Deserialize)]
struct JobInfo {
    id: String,
    state: String,
}

#[derive(Deserialize)]
struct BuildingMetadata {
    egid: String,
    egrid: Option<String>,
    status: String,
    construction: ConstructionDetails,
    physical_characteristics: PhysicalCharacteristics,
    energy_systems: EnergySystems,
    location: LocationData,
}

#[derive(Deserialize)]
struct ConstructionDetails {
    year: Option<i32>,
    month: Option<i32>,
    category: Option<String>,
    class: Option<String>,
}

#[derive(Deserialize)]
struct EnergySystems {
    reference_area: Option<f64>,
    heating_system_count: i32,
    hot_water_system_count: i32,
    primary_heating: HeatingSystem,
}

#[derive(Deserialize)]
struct LocationData {
    canton: Option<String>,
    municipality_name: Option<String>,
    coordinates: Coordinates,
    cadastral: CadastralData,
}

#[derive(Deserialize)]
struct CadastralData {
    lgbkr: Option<String>,  // Land registry district
    lparz: Option<String>,  // Property number
    egrid: Option<String>,  // Property identifier
}

#[derive(Deserialize)]
struct AddressWithBuilding {
    address: SchemaOrgAddress,
    building: BuildingMetadata,
}

#[derive(Deserialize)]
struct BuildingsResponse {
    query: String,
    count: i32,
    buildings: Vec<BuildingWithMetadata>,
}

pub struct SwissBuildingsClient {
    client: Client,
    base_url: String,
}

impl SwissBuildingsClient {
    pub fn new() -> Self {
        Self {
            client: Client::new(),
            base_url: "http://swiss-buildings_app:80".to_string(),
        }
    }

    /// Get complete building metadata by EGID (Federal Building ID)
    pub async fn get_building_by_egid(&self, egid: &str) -> Result<BuildingMetadata> {
        let building = self.client
            .get(&format!("{}/buildings/egid/{}", self.base_url, egid))
            .send()
            .await?
            .json::<BuildingMetadata>()
            .await?;
        
        Ok(building)
    }
    
    /// Get building by EGRID (Property identifier)
    pub async fn get_building_by_egrid(&self, egrid: &str) -> Result<BuildingMetadata> {
        let building = self.client
            .get(&format!("{}/buildings/egrid/{}", self.base_url, egrid))
            .send()
            .await?
            .json::<BuildingMetadata>()
            .await?;
        
        Ok(building)
    }
    
    /// Search buildings by address with complete metadata
    pub async fn search_buildings_by_address(&self, address: &str, limit: u32) -> Result<BuildingsResponse> {
        let buildings = self.client
            .get(&format!("{}/buildings/address", self.base_url))
            .query(&[("adresse", address), ("limit", &limit.to_string())])
            .send()
            .await?
            .json::<BuildingsResponse>()
            .await?;
        
        Ok(buildings)
    }
    
    /// Get address with complete building metadata
    pub async fn get_address_with_building(&self, address_uuid: &str) -> Result<AddressWithBuilding> {
        let address_with_building = self.client
            .get(&format!("{}/addresses/{}/building", self.base_url, address_uuid))
            .send()
            .await?
            .json::<AddressWithBuilding>()
            .await?;
        
        Ok(address_with_building)
    }
    
    /// Resolve building IDs to complete metadata (async job)
    pub async fn resolve_building_ids(&self, egids: Vec<String>) -> Result<JobInfo> {
        let csv = format!("egid\n{}", egids.join("\n"));
        
        let job = self.client
            .post(&format!("{}/resolve/building-ids", self.base_url))
            .header("Content-Type", "text/csv")
            .body(csv)
            .send()
            .await?
            .json::<JobInfo>()
            .await?;
            
        Ok(job)
    }
    
    /// Get job results with complete building metadata
    pub async fn get_job_results(&self, job_id: &str) -> Result<Vec<BuildingMetadata>> {
        let results = self.client
            .get(&format!("{}/resolve/jobs/{}/results", self.base_url, job_id))
            .send()
            .await?
            .json::<JobResults>()
            .await?;
            
        Ok(results.results)
    }
}
```