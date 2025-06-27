# Swiss Buildings API - Project Overview for AI Assistants

## 🎯 Purpose
This project provides a REST API for Swiss and Liechtenstein building registry data (GWR - Gebäude- und Wohnungsregister). It enables address searches, building lookups by EGID, and bulk resolution services.

## 🏗️ Architecture
- **Framework**: Symfony 7.2 (PHP 8.4)
- **Database**: PostgreSQL 16 with PostGIS extension
- **Search Engine**: Meilisearch v1.13
- **Container**: Docker with S6 process supervisor
- **Deployment**: Docker Swarm on production

## 📁 Key Project Structure
```
open-swiss-buildings-api/
├── src/                    # Symfony application code
├── config/                 # Symfony configuration
├── docker/                 # Docker container configuration
├── compose.final.yaml      # Production Docker Swarm config
├── deploy-to-swarm.sh      # Automated deployment script
├── restart-workers.sh      # Manual worker restart utility
├── test-api.sh            # API functionality test script
├── rust-api-integration.md # Integration guide for Rust services
├── .env.production        # Environment template
└── init/                  # AI assistant documentation
```

## 🔧 Key Components

### Services (Docker Swarm)
1. **swiss-buildings_app** - Main API application (PHP-FPM + Nginx)
2. **swiss-buildings_database** - PostgreSQL with PostGIS
3. **swiss-buildings_meilisearch** - Search engine
4. **swiss-buildings_worker-monitor** - Automatic worker management

### Background Workers
- **Resolver Worker**: Processes bulk resolution jobs (CSV → building data)
- **Async Worker**: Handles general async tasks and search indexing
- **Scheduler**: Weekly data refresh from government sources

### Data Sources
- **Swiss Federal Data**: ~3.2M building entries
- **Liechtenstein Data**: Additional building entries
- **Weekly Updates**: Automatic refresh every Monday

## 🚀 Deployment

### Prerequisites
- Docker Swarm cluster
- `.env` file with credentials (copy from `.env.production`)
- Active Docker context to production server

### Deploy Command
```bash
./deploy-to-swarm.sh
```

This automatically:
1. Deploys all 4 services
2. Fixes environment variable substitution issues
3. Starts worker monitor for automatic worker management
4. Ensures full functionality within 2-3 minutes

## 🔄 Known Issues & Solutions

### Environment Variable Substitution
Docker Swarm doesn't properly substitute variables in stack deploy. The deployment script automatically fixes this by updating services post-deployment.

### Worker Startup Timing
Workers may fail initially due to database not being ready. The worker-monitor service automatically retries and ensures workers start once the API is healthy.

### S6 Process Supervisor
The container uses S6 for process management. Workers are configured to restart automatically, but timing issues are handled by our worker-monitor service.

## 📊 Data Management
- **Initial Import**: Not needed if volumes exist
- **Data Persistence**: Via Docker volumes
- **Backup Strategy**: Not critical - data can be re-downloaded from government sources
- **Search Index**: Automatically maintained by workers

## 🔗 Integration Points
- **Internal API URL**: `http://swiss-buildings_app:80`
- **Network**: `swiss-buildings_default` (overlay)
- **No External Access**: Internal Swarm network only

## 🛠️ Maintenance Commands
```bash
# Check service status
docker service ls | grep swiss-buildings

# View logs
docker service logs swiss-buildings_app -f
docker service logs swiss-buildings_worker-monitor -f

# Manual worker restart (if needed)
./restart-workers.sh

# Test API functionality
./test-api.sh
```

## 📝 Important Notes
1. All 12 API endpoints require workers to be running
2. Workers auto-restart every hour for stability
3. Database credentials must match between all services
4. Meilisearch requires master key in production
5. Container restarts are fully automated - no manual intervention needed