# Swiss Buildings API - Rust Integration Guide

## Internal Deployment (No Traefik)

Swiss Buildings API provides Swiss and Liechtenstein building registry data as an internal service.

## Deployment Status

**Current Status**: ✅ Successfully deployed on Ecolution server
- ✅ PostgreSQL database: Running
- ✅ Meilisearch search: Running  
- ✅ API service: Running (1/1) - Web server active!

```bash
# ✅ Deployment is complete! Check status:
docker service ls | grep swiss-buildings

# All services should show 1/1 replicas
# View API logs
docker service logs swiss-buildings_app
```

## Connecting from Your Rust API

### Option 1: Direct Service Connection (Recommended)
```rust
// Connect using Docker Swarm service name
let api_url = "http://swiss-buildings_swiss-buildings-api:80";

// The service name format is: {stack_name}_{service_name}
// So: swiss-buildings_swiss-buildings-api
```

### Option 2: Attach Rust Container to Network
```yaml
# In your Rust API docker-compose:
services:
  rust-api:
    networks:
      - swiss-buildings_swiss-buildings-internal
      - other-networks

networks:
  swiss-buildings_swiss-buildings-internal:
    external: true
```

Then in Rust:
```rust
// Use short service names
let api_url = "http://swiss-buildings-api:80";
```

## API Endpoints for Your Rust Gateway

### 1. Address Autocomplete
```rust
// GET /address-search/find?query=Limmatstr&limit=10
let response = client
    .get(format!("{}/address-search/find", api_url))
    .query(&[("query", "Limmatstr"), ("limit", "10")])
    .send()
    .await?;
```

### 2. Get Address by ID
```rust
// GET /addresses/{id}
let address_id = "018ef6f9-5301-72f0-a0e6-c4170dcdade0";
let url = format!("{}/addresses/{}", api_url, address_id);
```

### 3. Resolve Building by EGID (Async Job)
```rust
// POST /resolve/building-ids
let csv_data = "egid\n9011206\n9083913";
let response = client
    .post(format!("{}/resolve/building-ids", api_url))
    .header("Content-Type", "text/csv")
    .body(csv_data)
    .send()
    .await?;

// Returns job info with ID
let job: JobInfo = response.json().await?;

// Poll for results
loop {
    let status = client
        .get(format!("{}/resolve/jobs/{}", api_url, job.id))
        .send()
        .await?;
    
    if status.state == "succeeded" {
        // Get results
        let results = client
            .get(format!("{}/resolve/jobs/{}/results", api_url, job.id))
            .send()
            .await?;
        break;
    }
    
    tokio::time::sleep(Duration::from_secs(1)).await;
}
```

### 4. List All Addresses (Paginated)
```rust
// GET /addresses?limit=100&offset=0
let response = client
    .get(format!("{}/addresses", api_url))
    .query(&[("limit", "100"), ("offset", "0")])
    .send()
    .await?;
```

## Service Discovery

The Swiss Buildings API stack exposes:
- **swiss-buildings-api**: Main API service (port 80)
- **swiss-buildings-db**: PostgreSQL with PostGIS (port 5432) - internal only
- **swiss-buildings-search**: Meilisearch engine (port 7700) - internal only

## Health Check
```rust
// GET /ping
let health = client
    .get(format!("{}/ping", api_url))
    .send()
    .await?;

if health.status() == 200 {
    println!("Swiss Buildings API is healthy");
}
```

## Notes

1. **No External Access**: This setup has no Traefik labels, so the API is only accessible within the Swarm network
2. **Scaling**: The app service is set to 2 replicas for redundancy
3. **Service Discovery**: Docker Swarm handles load balancing between replicas automatically
4. **Network Isolation**: Set `internal: true` in the network config for complete isolation if needed

## Example Rust Client Trait

```rust
#[async_trait]
trait SwissBuildingsClient {
    async fn search_addresses(&self, query: &str, limit: u32) -> Result<Vec<Address>>;
    async fn get_address(&self, id: &str) -> Result<Option<Address>>;
    async fn resolve_building_ids(&self, egids: Vec<String>) -> Result<JobId>;
    async fn get_job_results(&self, job_id: &str) -> Result<JobResults>;
}
```