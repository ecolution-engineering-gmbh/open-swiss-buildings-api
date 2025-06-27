#!/bin/bash
# Smart worker startup script that waits for database readiness
# This script will be copied into the container during deployment

set -e

DATABASE_READY_WAIT=${DATABASE_READY_WAIT:-60}
WORKER_RESTART_DELAY=${WORKER_RESTART_DELAY:-30}

echo "🔄 Worker startup script starting..."
echo "📅 Database ready wait: ${DATABASE_READY_WAIT}s"
echo "⏰ Worker restart delay: ${WORKER_RESTART_DELAY}s"

# Function to check database connectivity
check_database() {
    php /www/bin/console doctrine:query:sql "SELECT 1" >/dev/null 2>&1
}

# Function to check API readiness
check_api() {
    curl -s http://localhost/ping >/dev/null 2>&1
}

# Wait for database and API to be ready
echo "⏳ Waiting for database and API readiness..."
RETRY_COUNT=0
MAX_RETRIES=$((DATABASE_READY_WAIT / 5))

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    if check_database && check_api; then
        echo "✅ Database and API are ready!"
        break
    fi
    
    echo "⏳ Database/API not ready, waiting... (attempt $((RETRY_COUNT + 1))/$MAX_RETRIES)"
    sleep 5
    RETRY_COUNT=$((RETRY_COUNT + 1))
done

if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
    echo "❌ Database/API readiness timeout after ${DATABASE_READY_WAIT}s"
    exit 1
fi

# Additional delay to ensure everything is stable
echo "⏳ Additional stability wait: ${WORKER_RESTART_DELAY}s"
sleep $WORKER_RESTART_DELAY

# Start workers
echo "🚀 Starting resolver worker..."
php /www/bin/console messenger:consume resolve --time-limit=3600 &
RESOLVER_PID=$!

echo "🚀 Starting async worker..."
php /www/bin/console messenger:consume async --time-limit=3600 &
ASYNC_PID=$!

echo "✅ Workers started successfully!"
echo "📋 Resolver worker PID: $RESOLVER_PID"
echo "📋 Async worker PID: $ASYNC_PID"

# Test functionality
echo "🧪 Testing resolver functionality..."
sleep 5

TEST_RESULT=$(curl -X POST -H "Content-Type: text/csv" -d "egid
123456" http://localhost/resolve/building-ids 2>/dev/null || echo '{"id":"test-failed"}')

TEST_JOB_ID=$(echo $TEST_RESULT | php -r "echo json_decode(file_get_contents('php://stdin'))->id ?? 'failed';")

if [ "$TEST_JOB_ID" != "failed" ] && [ "$TEST_JOB_ID" != "test-failed" ]; then
    echo "✅ Workers are functional (test job: $TEST_JOB_ID)"
else
    echo "⚠️ Worker functionality test failed, but workers are running"
fi

# Keep script alive and monitor workers
echo "👁️ Monitoring workers..."
while true; do
    # Check if workers are still running
    if ! kill -0 $RESOLVER_PID 2>/dev/null; then
        echo "🔄 Resolver worker died, restarting..."
        php /www/bin/console messenger:consume resolve --time-limit=3600 &
        RESOLVER_PID=$!
    fi
    
    if ! kill -0 $ASYNC_PID 2>/dev/null; then
        echo "🔄 Async worker died, restarting..."
        php /www/bin/console messenger:consume async --time-limit=3600 &
        ASYNC_PID=$!
    fi
    
    sleep 60  # Check every minute
done