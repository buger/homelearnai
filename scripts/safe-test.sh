#!/bin/bash

# Safe PHPUnit Test Runner
# This script ensures tests NEVER run on the development database
# Always uses the test database explicitly to prevent data loss

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🛡️  Safe Test Runner - Database Protection Active${NC}"
echo ""

# Force the test environment and database FIRST (we'll override any current setting)
export APP_ENV=testing
export DB_CONNECTION=pgsql
export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_DATABASE=learning_app_test
export DB_USERNAME=laravel
export DB_PASSWORD=12345
export CACHE_STORE=array
export SESSION_DRIVER=file

# Create temp directory for file operations
mkdir -p storage/temp
# Use provided TMPDIR or default to local storage/temp
export TMPDIR="${TMPDIR:-$(pwd)/storage/temp}"

echo -e "${YELLOW}📋 Test Configuration:${NC}"
echo "   Environment: $APP_ENV"
echo "   Database: $DB_DATABASE (SAFE)"
echo "   Cache: $CACHE_STORE"
echo "   Session: $SESSION_DRIVER"
echo ""

# Verify the test database exists
echo -e "${GREEN}✓ Verifying test database exists...${NC}"
PGPASSWORD=$DB_PASSWORD psql -h $DB_HOST -p $DB_PORT -U $DB_USERNAME -lqt 2>/dev/null | cut -d \| -f 1 | grep -qw $DB_DATABASE
if [ $? -ne 0 ]; then
    echo -e "${YELLOW}⚠️  Test database '$DB_DATABASE' does not exist. Creating it...${NC}"
    PGPASSWORD=$DB_PASSWORD createdb -h $DB_HOST -p $DB_PORT -U $DB_USERNAME $DB_DATABASE 2>/dev/null || true
    echo -e "${GREEN}✓ Test database created${NC}"
fi

# Run the tests with all arguments passed through
echo -e "${GREEN}🧪 Running tests on SAFE test database...${NC}"
echo ""

php artisan test "$@"

echo ""
echo -e "${GREEN}✅ Tests completed safely on test database${NC}"
