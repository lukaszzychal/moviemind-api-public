#!/bin/bash

# MovieMind API - Etap 3: Manual Testing Script
# Tests metadata synchronization (actors/crew) from TMDB
# Usage: ./test-etap3-sync-metadata.sh [base_url]
# Example: ./test-etap3-sync-metadata.sh http://localhost:8000

BASE_URL="${1:-http://localhost:8000}"
API_URL="${BASE_URL}/api/v1"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Test movie slugs
TEST_MOVIES=("the-matrix-1999" "inception-2010" "pulp-fiction-1994")

echo -e "${BLUE}=== MovieMind API - Etap 3: Metadata Sync Testing ===${NC}\n"
echo "Base URL: ${BASE_URL}"
echo ""

# Helper function to make requests
make_request() {
    local method=$1
    local endpoint=$2
    local data=$3
    local description=$4
    
    echo -e "${CYAN}→ ${description}${NC}"
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
        echo "$body" | jq '.' 2>/dev/null || echo "$body"
    elif [ "$http_code" -ge 300 ] && [ "$http_code" -lt 400 ]; then
        echo -e "  ${YELLOW}⚠ Status: ${http_code} (Disambiguation)${NC}"
        echo "$body" | jq '.' 2>/dev/null || echo "$body"
    else
        echo -e "  ${RED}✗ Status: ${http_code}${NC}"
        echo "$body" | jq '.' 2>/dev/null || echo "$body"
    fi
    
    echo ""
    return 0
}

# Check if jq is installed
if ! command -v jq &> /dev/null; then
    echo -e "${YELLOW}Warning: jq is not installed. JSON output will not be formatted.${NC}"
    echo "Install jq: brew install jq (macOS) or apt-get install jq (Linux)"
    echo ""
fi

# ============================================================================
# SCENARIUSZ 1: Utworzenie filmu z TMDB i synchronizacja aktorów
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}SCENARIUSZ 1: Utworzenie filmu z TMDB i synchronizacja aktorów${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

TEST_SLUG="the-matrix-1999"

echo -e "${YELLOW}Krok 1: Pobieranie filmu (jeśli nie istnieje, zostanie utworzony z TMDB)${NC}"
make_request "GET" "/movies/${TEST_SLUG}" "" "Get movie: ${TEST_SLUG}"

echo -e "${YELLOW}Krok 2: Sprawdzanie czy film ma zsynchronizowanych aktorów${NC}"
PEOPLE_RESPONSE=$(curl -s "${API_URL}/movies/${TEST_SLUG}" -H "Accept: application/json")
PEOPLE_COUNT=$(echo "$PEOPLE_RESPONSE" | jq -r '.people | length // 0' 2>/dev/null || echo "0")

if [ "$PEOPLE_COUNT" -gt 0 ]; then
    echo -e "  ${GREEN}✓ Film ma ${PEOPLE_COUNT} powiązanych osób${NC}"
    echo "$PEOPLE_RESPONSE" | jq '.people[0:3]' 2>/dev/null || echo "  (Brak jq - nie można sformatować JSON)"
else
    echo -e "  ${YELLOW}⚠ Film nie ma jeszcze zsynchronizowanych osób${NC}"
    echo -e "  ${CYAN}Info: Job SyncMovieMetadataJob powinien być w kolejce${NC}"
    echo -e "  ${CYAN}Uruchom: cd api && php artisan queue:work${NC}"
fi

echo ""

# ============================================================================
# SCENARIUSZ 2: Endpoint /refresh NIE synchronizuje aktorów
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}SCENARIUSZ 2: Endpoint /refresh NIE synchronizuje aktorów${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

echo -e "${YELLOW}Krok 1: Sprawdzanie liczby aktorów PRZED refresh${NC}"
BEFORE_RESPONSE=$(curl -s "${API_URL}/movies/${TEST_SLUG}" -H "Accept: application/json")
BEFORE_COUNT=$(echo "$BEFORE_RESPONSE" | jq -r '.people | length // 0' 2>/dev/null || echo "0")
echo -e "  ${CYAN}Aktorów przed refresh: ${BEFORE_COUNT}${NC}"

echo -e "${YELLOW}Krok 2: Wywołanie endpointu /refresh${NC}"
make_request "POST" "/movies/${TEST_SLUG}/refresh" "" "Refresh movie metadata"

echo -e "${YELLOW}Krok 3: Sprawdzanie liczby aktorów PO refresh${NC}"
sleep 1  # Daj chwilę na przetworzenie
AFTER_RESPONSE=$(curl -s "${API_URL}/movies/${TEST_SLUG}" -H "Accept: application/json")
AFTER_COUNT=$(echo "$AFTER_RESPONSE" | jq -r '.people | length // 0' 2>/dev/null || echo "0")
echo -e "  ${CYAN}Aktorów po refresh: ${AFTER_COUNT}${NC}"

if [ "$BEFORE_COUNT" -eq "$AFTER_COUNT" ]; then
    echo -e "  ${GREEN}✓ SUCCESS: Liczba aktorów nie zmieniła się (refresh nie synchronizuje aktorów)${NC}"
else
    echo -e "  ${RED}✗ FAIL: Liczba aktorów się zmieniła (refresh nie powinien synchronizować aktorów)${NC}"
fi

echo ""

# ============================================================================
# SCENARIUSZ 3: Sprawdzenie synchronizacji crew (reżyser, scenarzysta)
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}SCENARIUSZ 3: Sprawdzenie synchronizacji crew (reżyser, scenarzysta)${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

CREW_SLUG="inception-2010"

echo -e "${YELLOW}Krok 1: Pobieranie filmu z crew${NC}"
make_request "GET" "/movies/${CREW_SLUG}" "" "Get movie: ${CREW_SLUG}"

echo -e "${YELLOW}Krok 2: Sprawdzanie czy crew jest zsynchronizowany${NC}"
CREW_RESPONSE=$(curl -s "${API_URL}/movies/${CREW_SLUG}" -H "Accept: application/json")
CREW_DIRECTORS=$(echo "$CREW_RESPONSE" | jq '[.people[]? | select(.role == "DIRECTOR")]' 2>/dev/null || echo "[]")
CREW_WRITERS=$(echo "$CREW_RESPONSE" | jq '[.people[]? | select(.role == "WRITER")]' 2>/dev/null || echo "[]")

DIRECTOR_COUNT=$(echo "$CREW_DIRECTORS" | jq 'length' 2>/dev/null || echo "0")
WRITER_COUNT=$(echo "$CREW_WRITERS" | jq 'length' 2>/dev/null || echo "0")

if [ "$DIRECTOR_COUNT" -gt 0 ] || [ "$WRITER_COUNT" -gt 0 ]; then
    echo -e "  ${GREEN}✓ Film ma zsynchronizowany crew${NC}"
    echo -e "  ${CYAN}Reżyserzy: ${DIRECTOR_COUNT}${NC}"
    echo -e "  ${CYAN}Scenarzyści: ${WRITER_COUNT}${NC}"
    if [ "$DIRECTOR_COUNT" -gt 0 ]; then
        echo "$CREW_DIRECTORS" | jq '.' 2>/dev/null || echo "  (Brak jq)"
    fi
else
    echo -e "  ${YELLOW}⚠ Film nie ma jeszcze zsynchronizowanego crew${NC}"
    echo -e "  ${CYAN}Info: Uruchom queue worker aby zsynchronizować: cd api && php artisan queue:work${NC}"
fi

echo ""

# ============================================================================
# SCENARIUSZ 4: Sprawdzenie czy tmdb_id NIE jest widoczny w API
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}SCENARIUSZ 4: Sprawdzenie czy tmdb_id NIE jest widoczny w API${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

echo -e "${YELLOW}Krok 1: Sprawdzanie odpowiedzi filmu${NC}"
MOVIE_RESPONSE=$(curl -s "${API_URL}/movies/${TEST_SLUG}" -H "Accept: application/json")
HAS_TMDB_ID=$(echo "$MOVIE_RESPONSE" | jq 'has("tmdb_id")' 2>/dev/null || echo "false")

if [ "$HAS_TMDB_ID" = "false" ]; then
    echo -e "  ${GREEN}✓ Film nie zawiera tmdb_id w odpowiedzi API${NC}"
else
    echo -e "  ${RED}✗ Film zawiera tmdb_id w odpowiedzi API (nie powinien)${NC}"
fi

echo -e "${YELLOW}Krok 2: Sprawdzanie odpowiedzi osób${NC}"
if [ "$PEOPLE_COUNT" -gt 0 ]; then
    FIRST_PERSON_HAS_TMDB=$(echo "$MOVIE_RESPONSE" | jq '.people[0] | has("tmdb_id")' 2>/dev/null || echo "false")
    if [ "$FIRST_PERSON_HAS_TMDB" = "false" ]; then
        echo -e "  ${GREEN}✓ Osoby nie zawierają tmdb_id w odpowiedzi API${NC}"
    else
        echo -e "  ${RED}✗ Osoby zawierają tmdb_id w odpowiedzi API (nie powinny)${NC}"
    fi
else
    echo -e "  ${YELLOW}⚠ Brak osób do sprawdzenia${NC}"
fi

echo ""

# ============================================================================
# PODSUMOWANIE
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}PODSUMOWANIE${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"

echo -e "${CYAN}Przydatne komendy:${NC}"
echo -e "  ${YELLOW}• Sprawdź logi:${NC} tail -f api/storage/logs/laravel.log | grep SyncMovieMetadataJob"
echo -e "  ${YELLOW}• Uruchom queue worker:${NC} cd api && php artisan queue:work"
echo -e "  ${YELLOW}• Sprawdź Horizon:${NC} ${BASE_URL}/horizon"
echo -e "  ${YELLOW}• Sprawdź failed jobs:${NC} cd api && php artisan queue:failed"
echo ""
echo -e "${CYAN}Sprawdź w bazie danych:${NC}"
echo -e "  ${YELLOW}• cd api && php artisan tinker${NC}"
echo -e "  ${YELLOW}• \$movie = \\App\\Models\\Movie::where('slug', '${TEST_SLUG}')->first();${NC}"
echo -e "  ${YELLOW}• \$movie->people->each(function(\$p) { echo \$p->name . ' (tmdb_id: ' . \$p->tmdb_id . ')\\n'; });${NC}"
echo ""

