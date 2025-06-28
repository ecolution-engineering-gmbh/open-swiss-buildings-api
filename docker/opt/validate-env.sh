#!/bin/sh
set -e

echo "🔍 Validating required environment variables..."

# Required environment variables for Swiss Buildings API
REQUIRED_VARS="APP_ENV DATABASE_URL APP_SECRET MEILISEARCH_DSN MESSENGER_TRANSPORT_DSN REGISTRY_DATABASE_CH_FILE"

# Optional but recommended variables
OPTIONAL_VARS="S6_BEHAVIOUR_IF_STAGE2_FAILS S6_CMD_WAIT_FOR_SERVICES_MAXTIME DATABASE_READY_WAIT WORKER_RESTART_DELAY"

# Track validation status
VALIDATION_FAILED=0

# Check required variables
for var in $REQUIRED_VARS; do
    if [ -z "$(eval echo \$$var)" ]; then
        echo "❌ Missing required environment variable: $var"
        VALIDATION_FAILED=1
    else
        echo "✅ $var is set"
    fi
done

# Check optional variables (warn but don't fail)
for var in $OPTIONAL_VARS; do
    if [ -z "$(eval echo \$$var)" ]; then
        echo "⚠️  Optional environment variable not set: $var"
    else
        echo "✅ $var is set"
    fi
done

# Validate specific values
if [ "$APP_ENV" != "prod" ] && [ "$APP_ENV" != "dev" ] && [ "$APP_ENV" != "test" ]; then
    echo "❌ APP_ENV must be one of: prod, dev, test. Current value: $APP_ENV"
    VALIDATION_FAILED=1
fi

# Validate DATABASE_URL format
if echo "$DATABASE_URL" | grep -qE "^postgresql://.*@.*:.*\/.*"; then
    echo "✅ DATABASE_URL format is valid (PostgreSQL)"
elif echo "$DATABASE_URL" | grep -qE "^sqlite:///.*"; then
    echo "✅ DATABASE_URL format is valid (SQLite)"
else
    echo "❌ DATABASE_URL format is invalid. Expected PostgreSQL or SQLite format."
    VALIDATION_FAILED=1
fi

# Validate MEILISEARCH_DSN format
if echo "$MEILISEARCH_DSN" | grep -qE "^http://.*:.*"; then
    echo "✅ MEILISEARCH_DSN format is valid"
else
    echo "❌ MEILISEARCH_DSN format is invalid. Expected format: http://host:port"
    VALIDATION_FAILED=1
fi

# Check if APP_SECRET is strong enough (at least 32 characters)
if [ ${#APP_SECRET} -lt 32 ]; then
    echo "⚠️  APP_SECRET should be at least 32 characters long for security"
fi

if [ $VALIDATION_FAILED -eq 1 ]; then
    echo ""
    echo "❌ Environment validation failed. Please fix the above issues."
    exit 1
fi

echo ""
echo "✅ All required environment variables are valid!"
echo "🚀 Swiss Buildings API is ready to start"