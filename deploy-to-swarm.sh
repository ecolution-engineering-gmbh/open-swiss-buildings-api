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
echo "   docker service logs swiss-buildings_swiss-buildings-api -f"
echo ""
echo "🔗 Internal access URL:"
echo "   http://swiss-buildings_swiss-buildings-api:80"