# Swiss Buildings API - Production Integration Guide

## Overview

The Swiss Buildings API provides access to 1,059,000+ Swiss building records and 171,000+ searchable addresses from the official GWR (Gebäude- und Wohnungsregister) registry. This internal service runs on your Ecolution Docker Swarm cluster.

## Deployment Status

**Current Status**: ✅ Production Ready on Ecolution
- ✅ **Database**: PostgreSQL with PostGIS (1M+ buildings)
- ✅ **Search**: Meilisearch (171K+ indexed addresses)  
- ✅ **API**: Web server with 12 endpoints
- ✅ **Workers**: Background job processing
- ✅ **Auto-refresh**: Weekly GWR data updates

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

### 🔍 **Search & Discovery**

#### 1. Address Autocomplete
```rust
// GET /address-search/find?query={text}&limit={n}
let response = client
    .get(format!("{}/address-search/find", api_url))
    .query(&[("query", "Basel"), ("limit", "10")])
    .send()
    .await?;

// Returns: {"hits": [{"score": 96, "place": {...}}]}
```

#### 2. Search Index Statistics
```rust
// GET /address-search/stats
let stats = client
    .get(format!("{}/address-search/stats", api_url))
    .send()
    .await?;

// Returns: {"status": "ok", "indexedAddresses": 171000}
```

### 📍 **Address Lookup**

#### 3. List All Addresses (Paginated)
```rust
// GET /addresses?limit={n}&offset={n}
let response = client
    .get(format!("{}/addresses", api_url))
    .query(&[("limit", "100"), ("offset", "0")])
    .send()
    .await?;

// Returns: {"total": 171000, "results": [...]}
```

#### 4. Get Specific Address
```rust
// GET /addresses/{id}
let address_id = "0197b276-a609-7b1d-8c68-42248c5a6717";
let address = client
    .get(format!("{}/addresses/{}", api_url, address_id))
    .send()
    .await?;

// Returns full address with coordinates and building details
```

### 🔧 **Bulk Resolution Services** (Async Jobs)

#### 5. Resolve Building IDs (EGID)
```rust
// POST /resolve/building-ids
let csv_data = "egid\n123456\n789012";
let job = client
    .post(format!("{}/resolve/building-ids", api_url))
    .header("Content-Type", "text/csv")
    .body(csv_data)
    .send()
    .await?
    .json::<JobInfo>()
    .await?;

// Returns: {"id": "job-uuid", "state": "created", ...}
```

#### 6. Resolve Address Text
```rust
// POST /resolve/address-search  
let csv_data = "address\nLimmatstrasse 1, Zurich\nBaselstrasse 5, Bern";
let job = client
    .post(format!("{}/resolve/address-search", api_url))
    .header("Content-Type", "text/csv")
    .body(csv_data)
    .send()
    .await?;
```

#### 7. Resolve GeoJSON Coordinates
```rust
// POST /resolve/geo-json
let geojson = r#"{"type": "FeatureCollection", "features": [...]}"#;
let job = client
    .post(format!("{}/resolve/geo-json", api_url))
    .header("Content-Type", "application/json")
    .body(geojson)
    .send()
    .await?;
```

#### 8. Resolve Municipality Codes
```rust
// POST /resolve/municipalities-codes
let csv_data = "municipality_code\n261\n1061";
let job = client
    .post(format!("{}/resolve/municipalities-codes", api_url))
    .header("Content-Type", "text/csv")
    .body(csv_data)
    .send()
    .await?;
```

### ⏱️ **Job Management**

#### 9. Check Job Status
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

#### 10. Get Job Results
```rust
// GET /resolve/jobs/{id}/results
let results = client
    .get(format!("{}/resolve/jobs/{}/results", api_url, job_id))
    .send()
    .await?;

// Returns: {"results": [{"building_id": "123456", "address": {...}}]}
```

### 🏥 **Health & Documentation**

#### 11. Health Check
```rust
// GET /ping
let health = client
    .get(format!("{}/ping", api_url))
    .send()
    .await?;

// Returns: 204 No Content (success)
```

#### 12. API Documentation
```bash
# OpenAPI/Swagger UI: GET /doc
# OpenAPI Spec JSON: GET /doc.json
curl http://swiss-buildings_app:80/doc
```

## Current Data Status

- **🏢 Buildings**: 1,059,000+ Swiss building entries
- **📍 Addresses**: 171,000+ searchable addresses  
- **🔄 Updates**: Automatic weekly refresh (Mondays)
- **⚡ Performance**: Instant search, async bulk processing

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

# Deploy initial stack
./deploy-to-swarm.sh

# Update existing deployment
docker stack deploy -c compose.final.yaml swiss-buildings

# Check service health
docker service ls | grep swiss-buildings
docker service logs swiss-buildings_app --tail 20

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

## Production Notes

1. **Internal Only**: No external access, Swarm network only
2. **Auto-scaling**: Workers restart every 10 processed jobs  
3. **Data Backup**: Not needed - weekly refresh from government sources
4. **Health Monitoring**: Use `/ping` endpoint (returns 204)
5. **Resource Usage**: ~500MB RAM, minimal CPU when idle
6. **Network**: Uses overlay network `swiss-buildings_default`

## Complete Rust Client Example

```rust
use serde::{Deserialize, Serialize};
use reqwest::Client;

#[derive(Deserialize)]
struct JobInfo {
    id: String,
    state: String,
}

#[derive(Deserialize)]
struct SearchResults {
    hits: Vec<AddressHit>,
}

#[derive(Deserialize)]
struct AddressHit {
    score: f64,
    place: Address,
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

    pub async fn search_addresses(&self, query: &str, limit: u32) -> Result<SearchResults> {
        let response = self.client
            .get(&format!("{}/address-search/find", self.base_url))
            .query(&[("query", query), ("limit", &limit.to_string())])
            .send()
            .await?;
        
        Ok(response.json().await?)
    }

    pub async fn resolve_building_ids(&self, egids: Vec<String>) -> Result<JobInfo> {
        let csv = format!("egid\n{}", egids.join("\n"));
        
        let response = self.client
            .post(&format!("{}/resolve/building-ids", self.base_url))
            .header("Content-Type", "text/csv")
            .body(csv)
            .send()
            .await?;
            
        Ok(response.json().await?)
    }
}
```