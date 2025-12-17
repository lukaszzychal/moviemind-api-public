#!/bin/bash

# MovieMind API Manual Testing Script
# Usage: ./test-api.sh [base_url]
# Example: ./test-api.sh http://localhost:8000

BASE_URL="${1:-http://localhost:8000}"
API_URL="${BASE_URL}/api/v1"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== MovieMind API Manual Testing ===${NC}\n"
echo "Base URL: ${BASE_URL}"
echo ""

# Helper function to make requests
make_request() {
    local method=$1
    local endpoint=$2
    local data=$3
    local description=$4
    
    echo -e "${YELLOW}→ ${description}${NC}"
    echo "  ${method} ${endpoint}"
    
    if [ -n "$data" ]; then
        response=$(curl -s -w "\n%{http_code}" -X "$method" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -d "$data" \
            "${API_URL}${endpoint}")
    else
        response=$(curl -s -w "\n%{http_code}" -X "$method" \
            -H "Accept: application/json" \
            "${API_URL}${endpoint}")
    fi
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        echo -e "  ${GREEN}✓ Status: ${http_code}${NC}"
    elif [ "$http_code" -ge 300 ] && [ "$http_code" -lt 400 ]; then
        echo -e "  ${YELLOW}⚠ Status: ${http_code} (Disambiguation)${NC}"
    else
        echo -e "  ${RED}✗ Status: ${http_code}${NC}"
    fi
    
    echo "$body" | jq '.' 2>/dev/null || echo "$body"
    echo ""
}

# 1. Health Check
echo -e "${BLUE}=== Health Check ===${NC}"
make_request "GET" "/health/openai" "" "OpenAI Health Check"

# 2. List Movies
echo -e "${BLUE}=== Movies ===${NC}"
make_request "GET" "/movies" "" "List all movies"
make_request "GET" "/movies?q=Matrix" "" "Search movies: 'Matrix'"

# 3. Advanced Search
echo -e "${BLUE}=== Advanced Search ===${NC}"
make_request "GET" "/movies/search?q=Matrix" "" "Search: Matrix"
make_request "GET" "/movies/search?q=Matrix&year=1999" "" "Search: Matrix (year 1999)"
make_request "GET" "/movies/search?q=Matrix&director=Wachowski" "" "Search: Matrix (director Wachowski)"
make_request "GET" "/movies/search?q=Matrix&page=1&per_page=5" "" "Search: Matrix (paginated)"

# 4. Get Movie
echo -e "${BLUE}=== Get Movie ===${NC}"
make_request "GET" "/movies/the-matrix-1999" "" "Get movie: the-matrix-1999"

# 5. Generate Description
echo -e "${BLUE}=== Generation ===${NC}"
generate_response=$(make_request "POST" "/generate" \
    '{"entity_type":"MOVIE","slug":"the-matrix-1999","locale":"en-US","context_tag":"DEFAULT"}' \
    "Generate description: the-matrix-1999")

# Extract job_id if available
job_id=$(echo "$generate_response" | grep -o '"job_id":"[^"]*"' | cut -d'"' -f4)
if [ -n "$job_id" ]; then
    echo -e "${BLUE}=== Job Status ===${NC}"
    make_request "GET" "/jobs/${job_id}" "" "Get job status: ${job_id}"
fi

# 6. Disambiguation Example
echo -e "${BLUE}=== Disambiguation ===${NC}"
make_request "GET" "/movies/bad-boys" "" "Get movie: bad-boys (may trigger disambiguation)"
make_request "GET" "/movies/bad-boys?slug=bad-boys-ii-2003" "" "Select from disambiguation: bad-boys (slug=bad-boys-ii-2003)"

# 7. People
echo -e "${BLUE}=== People ===${NC}"
make_request "GET" "/people" "" "List all people"
make_request "GET" "/people?q=Keanu" "" "Search people: 'Keanu'"

# Summary
echo -e "${GREEN}=== Testing Complete ===${NC}"
echo ""
echo "Note: Some endpoints may return 202 (queued) or 300 (disambiguation)"
echo "Check job status with: GET ${API_URL}/jobs/{job_id}"
echo ""

# API Endpoints Reference
echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}=== API Endpoints Reference ===${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"
echo ""

# Movies
echo -e "${YELLOW}📽️  MOVIES${NC}"
echo -e "${GREEN}GET${NC}  ${API_URL}/movies"
echo "  Description: List all movies (with optional ?q=search query)"
echo "  Example: ${API_URL}/movies?q=Matrix"
echo ""

echo -e "${GREEN}GET${NC}  ${API_URL}/movies/search"
echo "  Description: Advanced movie search (local + TMDb)"
echo "  Query params: ?q=title&year=1999&director=Name&actor[]=Name1&actor[]=Name2&page=1&per_page=20"
echo "  Example: ${API_URL}/movies/search?q=Matrix&year=1999&page=1&per_page=10"
echo ""

echo -e "${GREEN}GET${NC}  ${API_URL}/movies/{slug}"
echo "  Description: Get movie by slug (may return disambiguation if ambiguous)"
echo "  Query params: ?description_id=123 (optional)"
echo "  Example: ${API_URL}/movies/the-matrix-1999"
echo "  Example: ${API_URL}/movies/matrix?slug=the-matrix-1999 (select from disambiguation)"
echo ""

echo -e "${GREEN}POST${NC} ${API_URL}/movies/{slug}/refresh"
echo "  Description: Refresh movie metadata from TMDb"
echo "  Example: curl -X POST ${API_URL}/movies/the-matrix-1999/refresh"
echo ""

# People
echo -e "${YELLOW}👤 PEOPLE${NC}"
echo -e "${GREEN}GET${NC}  ${API_URL}/people"
echo "  Description: List all people (with optional ?q=search query)"
echo "  Example: ${API_URL}/people?q=Keanu"
echo ""

echo -e "${GREEN}GET${NC}  ${API_URL}/people/{slug}"
echo "  Description: Get person by slug"
echo "  Query params: ?bio_id=123 (optional)"
echo "  Example: ${API_URL}/people/keanu-reeves"
echo ""

echo -e "${GREEN}POST${NC} ${API_URL}/people/{slug}/refresh"
echo "  Description: Refresh person metadata from TMDb"
echo "  Example: curl -X POST ${API_URL}/people/keanu-reeves/refresh"
echo ""

# Generation
echo -e "${YELLOW}🤖 GENERATION${NC}"
echo -e "${GREEN}POST${NC} ${API_URL}/generate"
echo "  Description: Queue AI description/bio generation"
echo "  Required body: {\"entity_type\":\"MOVIE\",\"slug\":\"the-matrix-1999\"}"
echo "  Optional body fields: \"locale\":\"en-US\", \"context_tag\":\"DEFAULT\""
echo "  entity_type: MOVIE | ACTOR | PERSON"
echo "  context_tag: DEFAULT | modern | critical | humorous"
echo "  Example: curl -X POST ${API_URL}/generate -H 'Content-Type: application/json' -d '{\"entity_type\":\"MOVIE\",\"slug\":\"the-matrix-1999\",\"locale\":\"en-US\",\"context_tag\":\"DEFAULT\"}'"
echo ""

# Jobs
echo -e "${YELLOW}📋 JOBS${NC}"
echo -e "${GREEN}GET${NC}  ${API_URL}/jobs/{id}"
echo "  Description: Get generation job status"
echo "  Example: ${API_URL}/jobs/7bec7007-7e93-4db5-afe4-0a96c490a16d"
echo ""

# Health
echo -e "${YELLOW}🏥 HEALTH${NC}"
echo -e "${GREEN}GET${NC}  ${API_URL}/health/openai"
echo "  Description: Check OpenAI API connectivity"
echo "  Example: ${API_URL}/health/openai"
echo ""

# Admin (with authentication)
echo -e "${YELLOW}🔐 ADMIN (requires Basic Auth)${NC}"
echo -e "${GREEN}GET${NC}  ${API_URL}/admin/flags"
echo "  Description: List all feature flags"
echo "  Example: curl -u admin:password ${API_URL}/admin/flags"
echo ""

echo -e "${GREEN}POST${NC} ${API_URL}/admin/flags/{name}"
echo "  Description: Set feature flag state"
echo "  Body: {\"state\":\"on\"} or {\"state\":\"off\"}"
echo "  Example: curl -u admin:password -X POST ${API_URL}/admin/flags/ai_description_generation -H 'Content-Type: application/json' -d '{\"state\":\"on\"}'"
echo ""

echo -e "${GREEN}GET${NC}  ${API_URL}/admin/flags/usage"
echo "  Description: Get feature flags usage statistics"
echo "  Example: curl -u admin:password ${API_URL}/admin/flags/usage"
echo ""

echo -e "${GREEN}GET${NC}  ${API_URL}/admin/debug/config"
echo "  Description: Get debug configuration (development only)"
echo "  Example: curl -u admin:password ${API_URL}/admin/debug/config"
echo ""

echo -e "${BLUE}════════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "${YELLOW}Response Codes:${NC}"
echo "  200 - Success"
echo "  202 - Generation queued (check job status)"
echo "  300 - Disambiguation required (multiple matches)"
echo "  404 - Not found"
echo "  422 - Validation error"
echo "  500 - Server error"
echo ""

