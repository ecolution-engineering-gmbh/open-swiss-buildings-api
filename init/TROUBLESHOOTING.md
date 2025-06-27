# Troubleshooting Guide

## 🔴 Common Issues

### 1. Workers Not Processing Jobs

**Symptoms:**
- Resolver jobs stuck in "created" state
- No job progression after submission

**Diagnosis:**
```bash
docker service logs swiss-buildings_worker-monitor --tail 20
docker exec $(docker ps --filter "name=swiss-buildings_app" -q) ps aux | grep messenger
```

**Solutions:**
1. Wait 2-3 minutes for automatic worker restart
2. Manual restart: `./restart-workers.sh`
3. Check database connectivity (see below)

### 2. Database Authentication Failures

**Symptoms:**
- "fe_sendauth: no password supplied" errors
- 500 errors on API endpoints

**Diagnosis:**
```bash
docker service inspect swiss-buildings_app --format '{{json .Spec.TaskTemplate.ContainerSpec.Env}}' | jq '.[] | select(contains("DATABASE_URL"))'
```

**Solutions:**
1. Re-run deployment: `./deploy-to-swarm.sh`
2. Manual fix:
```bash
source .env
docker service update --env-rm DATABASE_URL --env-add "DATABASE_URL=postgresql://app:$POSTGRES_PASSWORD@database:5432/app?serverVersion=16&charset=utf8" swiss-buildings_app
```

### 3. Meilisearch Failing to Start

**Symptoms:**
- Meilisearch service shows 0/1 replicas
- "Master key must be at least 16 bytes" errors

**Diagnosis:**
```bash
docker service logs swiss-buildings_meilisearch --tail 10
```

**Solutions:**
```bash
source .env
docker service update --env-rm MEILI_MASTER_KEY --env-add "MEILI_MASTER_KEY=$MEILI_MASTER_KEY" swiss-buildings_meilisearch
```

### 4. Empty Search Results

**Symptoms:**
- `/address-search/find` returns empty results
- `/address-search/stats` shows 0 indexed addresses

**Solutions:**
1. Check if data exists:
```bash
docker exec $(docker ps --filter "name=swiss-buildings_app" -q) \
  php bin/console doctrine:query:sql "SELECT COUNT(*) FROM building_entrance"
```

2. If no data, import:
```bash
# Download data
docker exec $(docker ps --filter "name=swiss-buildings_app" -q) \
  php bin/console app:registry:ch:download

# Import data (takes ~30 minutes)
docker exec -d $(docker ps --filter "name=swiss-buildings_app" -q) \
  php bin/console app:building-data:import --country-code=CH

# Index for search
docker exec -d $(docker ps --filter "name=swiss-buildings_app" -q) \
  php bin/console app:address-search:index-all
```

### 5. Container Restart Issues

**Symptoms:**
- Services don't recover after restart
- Workers not starting automatically

**Solutions:**
1. Check worker monitor is running:
```bash
docker service ls | grep worker-monitor
```

2. Force service update:
```bash
docker service update --force swiss-buildings_app
```

3. Re-deploy if needed:
```bash
./deploy-to-swarm.sh
```

## 🟡 Performance Issues

### Slow Resolution Jobs
- Normal: Large CSV files take time
- Check worker count: Should see 2 workers running
- Monitor job progress via API

### High Memory Usage
- Expected: ~500MB idle, up to 1GB during import
- PostGIS operations are memory-intensive
- Consider scaling if consistently high

## 🟢 Health Checks

### Quick Health Check
```bash
# All services running?
docker service ls | grep swiss-buildings

# API responding?
docker exec $(docker ps --filter "name=swiss-buildings_app" -q) \
  curl -s http://localhost/ping && echo "OK" || echo "FAIL"

# Workers running?
docker service logs swiss-buildings_worker-monitor --tail 5
```

### Comprehensive Test
```bash
./test-api.sh
```

## 📊 Monitoring Commands

```bash
# Service status
docker service ps swiss-buildings_app

# Container logs
docker service logs swiss-buildings_app -f

# Worker monitor logs
docker service logs swiss-buildings_worker-monitor -f

# Database logs
docker service logs swiss-buildings_database --tail 50

# Meilisearch logs
docker service logs swiss-buildings_meilisearch --tail 50
```

## 🔧 Recovery Procedures

### Full Reset (Nuclear Option)
```bash
# Remove everything
docker stack rm swiss-buildings
docker volume rm swiss-buildings_database_data
docker volume rm swiss-buildings_meilisearch_data

# Fresh deploy
./deploy-to-swarm.sh

# Re-import data
# (Follow step 4 above)
```

### Partial Reset (Keep Data)
```bash
docker stack rm swiss-buildings
sleep 10
./deploy-to-swarm.sh
```

## 💡 Prevention Tips

1. Always use `./deploy-to-swarm.sh` for deployments
2. Don't manually edit service environment variables
3. Let worker-monitor handle worker restarts
4. Keep `.env` file synchronized
5. Monitor logs after deployments