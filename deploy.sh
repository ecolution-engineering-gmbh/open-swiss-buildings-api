#!/bin/bash
# Swiss Buildings API Deployment Script

set -e

STACK_NAME="${STACK_NAME:-swiss-buildings}"

echo "🏗️  Deploying Swiss Buildings API..."

# Check if .env exists
if [ ! -f .env ]; then
    echo "❌ Error: .env file not found!"
    echo "Please copy .env.production to .env and fill in your values:"
    echo "  cp .env.production .env"
    exit 1
fi

# Load environment variables
export $(cat .env | grep -v '^#' | xargs)

# Validate required variables
required_vars=("APP_SECRET" "POSTGRES_PASSWORD" "MEILI_MASTER_KEY")
for var in "${required_vars[@]}"; do
    if [ -z "${!var}" ]; then
        echo "❌ Error: $var is not set in .env file!"
        exit 1
    fi
done

# Deploy the stack
echo "🚀 Deploying stack '$STACK_NAME'..."
docker stack deploy -c compose.prod.yaml $STACK_NAME

echo "⏳ Waiting for services to start..."
sleep 10

# Check deployment status
echo "📊 Service status:"
docker stack services $STACK_NAME

echo ""
echo "✅ Swiss Buildings API deployed successfully!"
echo ""
echo "🔗 Internal access from your Rust API:"
echo "   http://${STACK_NAME}_swiss-buildings-api:80"
echo ""
echo "📚 Available endpoints:"
echo "   GET  /ping                    - Health check"
echo "   GET  /address-search/find     - Address autocomplete"
echo "   GET  /addresses/{id}          - Get address by ID"
echo "   POST /resolve/building-ids    - Resolve EGIDs"
echo "   POST /resolve/address-search  - Resolve addresses"
echo ""
echo "💡 Commands:"
echo "   Logs:    docker service logs ${STACK_NAME}_swiss-buildings-api -f"
echo "   Scale:   docker service scale ${STACK_NAME}_swiss-buildings-api=3"
echo "   Remove:  docker stack rm $STACK_NAME"