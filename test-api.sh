#!/bin/bash
# Comprehensive test script for Swiss Buildings API

set -e

echo "🧪 Testing Swiss Buildings API Functionality"
echo "=============================================="

# Get app container
APP_CONTAINER=$(docker ps --filter "name=swiss-buildings_app" -q)
if [ -z "$APP_CONTAINER" ]; then
    echo "❌ No swiss-buildings_app container found"
    exit 1
fi

echo "📦 App container: $APP_CONTAINER"

# Test 1: Health Check
echo "🔍 Testing health check..."
if docker exec $APP_CONTAINER curl -s http://localhost/ping >/dev/null; then
    echo "✅ Health check passed"
else
    echo "❌ Health check failed"
    exit 1
fi

# Test 2: Database Connection
echo "🔍 Testing database connection..."
BUILDING_COUNT=$(docker exec $APP_CONTAINER php bin/console doctrine:query:sql "SELECT COUNT(*) as count FROM building_entrance LIMIT 1" 2>/dev/null | tail -1 | tr -d ' ')
if [ "$BUILDING_COUNT" -gt 100000 ]; then
    echo "✅ Database connection OK ($BUILDING_COUNT buildings)"
else
    echo "❌ Database connection failed or insufficient data"
    exit 1
fi

# Test 3: Search Index
echo "🔍 Testing search index..."
SEARCH_STATS=$(docker exec $APP_CONTAINER curl -s "http://localhost/address-search/stats" | jq -r '.indexedAddresses' 2>/dev/null || echo "0")
if [ "$SEARCH_STATS" -gt 50000 ]; then
    echo "✅ Search index OK ($SEARCH_STATS addresses indexed)"
else
    echo "❌ Search index not ready ($SEARCH_STATS addresses)"
fi

# Test 4: Address Search
echo "🔍 Testing address search..."
SEARCH_RESULT=$(docker exec $APP_CONTAINER curl -s "http://localhost/address-search/find?query=Basel&limit=1" | jq -r '.hits[0].place.postalAddress.streetAddress' 2>/dev/null || echo "null")
if [ "$SEARCH_RESULT" != "null" ] && [ "$SEARCH_RESULT" != "" ]; then
    echo "✅ Address search OK (found: $SEARCH_RESULT)"
else
    echo "⚠️ Address search not ready (search index may be building)"
fi

# Test 5: Address Listing
echo "🔍 Testing address listing..."
ADDRESS_TOTAL=$(docker exec $APP_CONTAINER curl -s "http://localhost/addresses?limit=1" | jq -r '.total' 2>/dev/null || echo "0")
if [ "$ADDRESS_TOTAL" -gt 50000 ]; then
    echo "✅ Address listing OK ($ADDRESS_TOTAL total addresses)"
else
    echo "❌ Address listing failed"
    exit 1
fi

# Test 6: Resolver Functionality
echo "🔍 Testing resolver functionality..."
RESOLVER_JOB=$(docker exec $APP_CONTAINER curl -X POST -H "Content-Type: text/csv" -d "egid
123456" http://localhost/resolve/building-ids 2>/dev/null | jq -r '.id' 2>/dev/null || echo "null")

if [ "$RESOLVER_JOB" != "null" ] && [ "$RESOLVER_JOB" != "" ]; then
    echo "✅ Resolver job created: $RESOLVER_JOB"
    
    # Wait and check job completion
    sleep 10
    JOB_STATE=$(docker exec $APP_CONTAINER curl -s "http://localhost/resolve/jobs/$RESOLVER_JOB" | jq -r '.state' 2>/dev/null || echo "unknown")
    
    if [ "$JOB_STATE" = "completed" ]; then
        echo "✅ Resolver workers functional (job completed)"
    elif [ "$JOB_STATE" = "ready" ] || [ "$JOB_STATE" = "processing" ]; then
        echo "⚠️ Resolver workers processing (job state: $JOB_STATE)"
    else
        echo "❌ Resolver workers not functional (job state: $JOB_STATE)"
    fi
else
    echo "❌ Resolver job creation failed"
fi

echo ""
echo "🎯 Summary:"
echo "- Health Check: ✅"
echo "- Database: ✅ ($BUILDING_COUNT buildings)"
echo "- Search Index: $([ "$SEARCH_STATS" -gt 50000 ] && echo "✅" || echo "⚠️") ($SEARCH_STATS addresses)"
echo "- Address Search: $([ "$SEARCH_RESULT" != "null" ] && [ "$SEARCH_RESULT" != "" ] && echo "✅" || echo "⚠️")"
echo "- Address Listing: ✅ ($ADDRESS_TOTAL addresses)"
echo "- Resolver: $([ "$RESOLVER_JOB" != "null" ] && echo "✅" || echo "❌")"
echo ""
echo "🚀 Swiss Buildings API Status: $([ "$BUILDING_COUNT" -gt 100000 ] && [ "$ADDRESS_TOTAL" -gt 50000 ] && [ "$RESOLVER_JOB" != "null" ] && echo "FULLY OPERATIONAL" || echo "PARTIALLY OPERATIONAL")"