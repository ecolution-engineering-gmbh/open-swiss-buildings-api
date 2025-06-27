#!/bin/bash

echo "🧪 Testing Swiss Buildings API - Building Metadata Endpoints"
echo "=============================================================="

# Get app container
APP_CONTAINER=$(docker ps --filter "name=swiss-buildings_app" -q | head -1)

if [ -z "$APP_CONTAINER" ]; then
    echo "❌ No swiss-buildings_app container found"
    exit 1
fi

echo "📦 App container: $APP_CONTAINER"
echo ""

# Test basic health first
echo "🔍 Testing health check..."
HEALTH_STATUS=$(docker exec $APP_CONTAINER curl -s -o /dev/null -w "%{http_code}" "http://localhost/ping")
if [ "$HEALTH_STATUS" = "204" ]; then
    echo "✅ Health check passed"
else
    echo "❌ Health check failed (HTTP $HEALTH_STATUS)"
    exit 1
fi

echo ""
echo "🏢 Testing Building Metadata Endpoints..."
echo ""

# Test building stats endpoint
echo "📊 Testing /buildings/stats..."
STATS_RESPONSE=$(docker exec $APP_CONTAINER curl -s "http://localhost/buildings/stats")
STATS_CODE=$(docker exec $APP_CONTAINER curl -s -o /dev/null -w "%{http_code}" "http://localhost/buildings/stats")

if [ "$STATS_CODE" = "200" ]; then
    echo "✅ Building stats endpoint working"
    echo "Response preview:"
    echo "$STATS_RESPONSE" | head -c 200
    echo "..."
elif [ "$STATS_CODE" = "503" ]; then
    echo "⚠️  Building stats endpoint responding (503 - metadata not imported yet)"
    echo "Response:"
    echo "$STATS_RESPONSE"
else
    echo "❌ Building stats endpoint failed (HTTP $STATS_CODE)"
    echo "Response: $STATS_RESPONSE"
fi

echo ""

# Test building by EGID (will fail until import is done, but route should exist)
echo "🔍 Testing /buildings/egid/150404..."
EGID_CODE=$(docker exec $APP_CONTAINER curl -s -o /dev/null -w "%{http_code}" "http://localhost/buildings/egid/150404")

if [ "$EGID_CODE" = "404" ]; then
    echo "✅ Building EGID endpoint working (404 expected until import)"
elif [ "$EGID_CODE" = "200" ]; then
    echo "🎉 Building EGID endpoint working with data!"
    EGID_RESPONSE=$(docker exec $APP_CONTAINER curl -s "http://localhost/buildings/egid/150404")
    echo "Response preview:"
    echo "$EGID_RESPONSE" | head -c 300
    echo "..."
else
    echo "❌ Building EGID endpoint failed (HTTP $EGID_CODE)"
fi

echo ""

# Test routes are registered
echo "🔍 Checking registered routes..."
ROUTES=$(docker exec $APP_CONTAINER php bin/console debug:router | grep buildings | wc -l)
if [ "$ROUTES" -gt 0 ]; then
    echo "✅ Building routes registered ($ROUTES routes found)"
    echo "Available building routes:"
    docker exec $APP_CONTAINER php bin/console debug:router | grep buildings
else
    echo "❌ No building routes found"
fi

echo ""

# Check database tables
echo "🗄️  Checking database tables..."
BUILDING_METADATA_EXISTS=$(docker exec $APP_CONTAINER php bin/console doctrine:query:sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_name = 'building_metadata'" 2>/dev/null | grep -o '[0-9]*' | tail -1)

if [ "$BUILDING_METADATA_EXISTS" = "1" ]; then
    echo "✅ building_metadata table exists"
    
    # Check if table has data
    METADATA_COUNT=$(docker exec $APP_CONTAINER php bin/console doctrine:query:sql "SELECT COUNT(*) FROM building_metadata" 2>/dev/null | grep -o '[0-9]*' | tail -1)
    if [ ! -z "$METADATA_COUNT" ] && [ "$METADATA_COUNT" -gt 0 ]; then
        echo "🎉 Building metadata table has $METADATA_COUNT records!"
    else
        echo "⚠️  Building metadata table is empty - run import command"
    fi
else
    echo "❌ building_metadata table not found"
fi

echo ""

# Check if building metadata import command is available
echo "🔧 Checking building metadata import command..."
IMPORT_CMD_EXISTS=$(docker exec $APP_CONTAINER php bin/console list | grep building-metadata:import | wc -l)
if [ "$IMPORT_CMD_EXISTS" -gt 0 ]; then
    echo "✅ Building metadata import command available"
    echo ""
    echo "📋 To populate building metadata, run:"
    echo "   docker exec $APP_CONTAINER php bin/console app:building-metadata:import --batch-size=1000"
else
    echo "❌ Building metadata import command not found"
fi

echo ""
echo "📊 Current building entrance stats:"
docker exec $APP_CONTAINER php bin/console app:building-data:stats

echo ""
echo "🏁 Building Metadata Test Complete!"
echo ""
echo "📋 Next Steps (if building metadata is empty):"
echo "   1. Wait for deployment to complete"
echo "   2. Run: docker exec $APP_CONTAINER php bin/console app:building-metadata:import"
echo "   3. Test endpoints again with: ./test-building-metadata.sh"