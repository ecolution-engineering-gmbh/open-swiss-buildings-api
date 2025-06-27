#!/bin/bash
# Script to restart Swiss Buildings API workers after deployment
# This ensures workers start correctly after the database is ready

set -e

echo "🔄 Starting Swiss Buildings API workers..."

# Wait for API to be ready
sleep 30

# Get the app container ID
APP_CONTAINER=$(docker ps --filter "name=swiss-buildings_app" -q)

if [ -z "$APP_CONTAINER" ]; then
    echo "❌ No swiss-buildings_app container found"
    exit 1
fi

echo "📦 Found app container: $APP_CONTAINER"

# Test if API is responding
if ! docker exec $APP_CONTAINER curl -s http://localhost/ping > /dev/null; then
    echo "❌ API not responding"
    exit 1
fi

echo "✅ API is responding"

# Start workers in background
echo "🚀 Starting resolver worker..."
docker exec -d $APP_CONTAINER php bin/console messenger:consume resolve --time-limit=3600

echo "🚀 Starting async worker..."  
docker exec -d $APP_CONTAINER php bin/console messenger:consume async --time-limit=3600

# Test resolver functionality
echo "🧪 Testing resolver functionality..."
JOB_RESULT=$(docker exec $APP_CONTAINER curl -X POST -H "Content-Type: text/csv" -d "egid
123456" http://localhost/resolve/building-ids 2>/dev/null)

JOB_ID=$(echo $JOB_RESULT | jq -r '.id')

if [ "$JOB_ID" != "null" ]; then
    echo "✅ Resolver job created: $JOB_ID"
    
    # Wait and check if job completes
    sleep 5
    JOB_STATE=$(docker exec $APP_CONTAINER curl -s http://localhost/resolve/jobs/$JOB_ID | jq -r '.state')
    
    if [ "$JOB_STATE" = "completed" ]; then
        echo "✅ Workers are functioning correctly!"
    else
        echo "⚠️  Job state: $JOB_STATE (may need more time)"
    fi
else
    echo "❌ Failed to create resolver job"
    exit 1
fi

echo "🎉 Swiss Buildings API workers started successfully!"