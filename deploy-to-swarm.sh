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