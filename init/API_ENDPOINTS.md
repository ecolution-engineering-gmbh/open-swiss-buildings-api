# API Endpoints Reference

## Base URL
Internal Swarm: `http://swiss-buildings_app:80`

## 🔍 Search & Discovery

### GET /address-search/find
Search for addresses with autocomplete functionality.

**Query Parameters:**
- `query` (required): Search text
- `limit` (optional): Max results (default: 10)

**Example:**
```bash
GET /address-search/find?query=Basel&limit=5
```

**Response:**
```json
{
  "hits": [{
    "score": 96,
    "place": {
      "identifier": "uuid",
      "postalAddress": {
        "addressCountry": "CH",
        "addressLocality": "Dagmersellen",
        "streetAddress": "Baselstrasse 3",
        "postalCode": "6252"
      },
      "geo": {
        "latitude": "47.213087",
        "longitude": "7.988720"
      }
    }
  }]
}
```

### GET /address-search/stats
Get search index statistics.

**Response:**
```json
{
  "status": "ok",
  "indexedAddresses": 253000
}
```

## 📍 Address Management

### GET /addresses
List all addresses with pagination.

**Query Parameters:**
- `limit`: Results per page (max: 1000)
- `offset`: Skip N results

**Response:**
```json
{
  "total": 1023000,
  "results": [...]
}
```

### GET /addresses/{id}
Get specific address by UUID.

**Response:**
```json
{
  "identifier": "uuid",
  "postalAddress": {...},
  "geo": {...},
  "additionalProperty": {
    "buildingId": "123456",
    "entranceId": "0",
    "municipalityCode": "261"
  }
}
```

## 🔧 Bulk Resolution (Async)

All resolver endpoints return a job that must be polled for results.

### POST /resolve/building-ids
Resolve building IDs (EGID) to full addresses.

**Headers:**
- `Content-Type: text/csv`

**Body:**
```csv
egid
123456
789012
```

### POST /resolve/address-search
Resolve address text to building data.

**Headers:**
- `Content-Type: text/csv`

**Body:**
```csv
address
Limmatstrasse 1, Zurich
Bahnhofstrasse 5, Bern
```

### POST /resolve/geo-json
Resolve coordinates to nearest buildings.

**Headers:**
- `Content-Type: application/json`

**Body:**
```json
{
  "type": "FeatureCollection",
  "features": [...]
}
```

### POST /resolve/municipalities-codes
Get all buildings in specified municipalities.

**Headers:**
- `Content-Type: text/csv`

**Body:**
```csv
municipality_code
261
1061
```

## ⏱️ Job Management

### GET /resolve/jobs/{id}
Check job status.

**Response:**
```json
{
  "id": "uuid",
  "type": "building_ids",
  "state": "completed|processing|failed|created",
  "created_at": "2025-06-27T19:00:00+0000",
  "expires_at": "2025-06-29T19:00:00+0000"
}
```

### GET /resolve/jobs/{id}/results
Get job results (only when state is "completed").

**Response:**
```json
{
  "results": [{
    "building_id": "123456",
    "confidence": 1,
    "match_type": "buildingId",
    "address": {
      "postal_code": "8902",
      "locality": "Urdorf",
      "street_name": "In der Fadmatt",
      "street_house_number": "51"
    }
  }]
}
```

## 🏥 System

### GET /ping
Health check endpoint.

**Response:** 204 No Content

### GET /doc
Swagger UI documentation.

### GET /doc.json
OpenAPI specification.

## 📝 Common Patterns

### Async Job Processing
1. Submit data to `/resolve/*` endpoint
2. Receive job ID in response
3. Poll `/resolve/jobs/{id}` until state is "completed"
4. Fetch results from `/resolve/jobs/{id}/results`

### Error Responses
- 400: Invalid request data
- 404: Resource not found
- 500: Internal server error (often worker-related)

### Rate Limiting
No built-in rate limiting, but be considerate with bulk operations.