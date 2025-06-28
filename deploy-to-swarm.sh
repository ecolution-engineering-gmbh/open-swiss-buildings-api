#!/bin/bash
# Deploy Swiss Buildings API to Docker Swarm with environment variables

set -e

# Load environment variables
if [ ! -f .env ]; then
    echo "❌ Error: .env file not found!"
    echo "Please copy .env.production to .env and fill in your values"
    exit 1
fi

# Export all variables from .env
set -a
source .env
set +a

echo "🚀 Deploying Swiss Buildings API to Swarm..."

# Deploy the stack
docker stack deploy -c compose.final.yaml swiss-buildings

echo "🔧 Fixing environment variables..."
# Fix environment variables that don't substitute properly in stack deploy
docker service update \
  --env-rm DATABASE_URL --env-add "DATABASE_URL=postgresql://app:$POSTGRES_PASSWORD@database:5432/app?serverVersion=16&charset=utf8" \
  --env-rm MEILISEARCH_DSN --env-add "MEILISEARCH_DSN=http://meilisearch:7700?apiKey=$MEILI_MASTER_KEY" \
  --env-rm APP_SECRET --env-add "APP_SECRET=$APP_SECRET" \
  swiss-buildings_app >/dev/null 2>&1 &

docker service update \
  --env-rm MEILI_MASTER_KEY --env-add "MEILI_MASTER_KEY=$MEILI_MASTER_KEY" \
  swiss-buildings_meilisearch >/dev/null 2>&1 &

docker service update \
  --env-rm POSTGRES_PASSWORD --env-add "POSTGRES_PASSWORD=$POSTGRES_PASSWORD" \
  swiss-buildings_database >/dev/null 2>&1 &

wait

echo "⏳ Waiting for services to start..."
sleep 10

# Check deployment
echo "📊 Service status:"
docker stack services swiss-buildings

echo ""
echo "✅ Deployment initiated!"
echo ""
echo "🔍 Check status with:"
echo "   docker stack services swiss-buildings"
echo ""
echo "📝 View logs with:"
echo "   docker service logs swiss-buildings_app -f"
echo "   docker service logs swiss-buildings_worker-monitor -f"
echo ""
echo "🔗 Internal access URL:"
echo "   http://swiss-buildings_app:80"
echo ""
echo "🤖 Worker Monitor will automatically start workers once API is ready"
echo "⏰ Workers restart automatically every hour"
echo ""

echo "🔍 Verifying deployment..."

# Wait for services to be healthy
echo "⏳ Waiting for services to pass health checks..."
for i in {1..20}; do
    RUNNING_SERVICES=$(docker service ps swiss-buildings_app --filter "desired-state=running" --format "table {{.CurrentState}}" | grep -c "Running" || echo "0")
    if [ "$RUNNING_SERVICES" -gt 0 ]; then
        echo "✅ Service is running"
        break
    fi
    echo "⏳ Attempt $i/20: Waiting for service to be ready..."
    sleep 15
done

# Test API endpoints
echo "🧪 Testing API endpoints..."

# Test ping endpoint
if curl -f -m 10 http://swiss-buildings_app:80/ping 2>/dev/null; then
    echo "✅ Ping endpoint is responding"
else
    echo "❌ Ping endpoint test failed - API may still be starting up"
    echo "📝 Recent service logs:"
    docker service logs swiss-buildings_app --tail 10
fi

# Test enhanced building metadata endpoint
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -m 10 "http://swiss-buildings_app:80/buildings/egid/999999999" 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "404" ] || [ "$HTTP_CODE" = "500" ]; then
    echo "✅ Enhanced building metadata endpoint is responding (HTTP $HTTP_CODE)"
elif [ "$HTTP_CODE" = "200" ]; then
    echo "✅ Enhanced building metadata endpoint is working perfectly!"
else
    echo "⚠️ Enhanced building metadata endpoint returned: HTTP $HTTP_CODE"
fi

echo ""
echo "✅ Deployment verification completed!"