#!/usr/bin/env bash
# Run manual test scenarios from MANUAL_TESTING_GUIDE and MANUAL_TEST_PLANS.
# Output: testdox-style summary + detailed report file.
set -e
BASE="${API_BASE_URL:-http://localhost:8000}/api/v1"
REPORT_DIR="${REPORT_DIR:-docs/qa}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
REPORT_FILE="$REPORT_DIR/MANUAL_SCENARIOS_RESULTS_${TIMESTAMP}.md"
PASSED=0
FAILED=0
TMPRESULTS=$(mktemp)
trap "rm -f $TMPRESULTS" EXIT

run_test() {
  local name="$1"
  local method="$2"
  local url="$3"
  local expected_status="${4:-200}"
  local extra_headers="${5:-}"
  local data="${6:-}"
  local status body request_line
  request_line="$method $url"
  [[ -n "$data" ]] && request_line="$request_line | body: $data"
  if [[ "$method" == "GET" ]]; then
    body=$(curl -s -w "\n%{http_code}" -X GET "$url" -H "Accept: application/json" $extra_headers 2>/dev/null || echo -e "\n000")
  elif [[ "$method" == "POST" ]]; then
    if [[ -n "$data" ]]; then
      body=$(curl -s -w "\n%{http_code}" -X POST "$url" -H "Accept: application/json" -H "Content-Type: application/json" -d "$data" 2>/dev/null || echo -e "\n000")
    else
      body=$(curl -s -w "\n%{http_code}" -X POST "$url" -H "Accept: application/json" 2>/dev/null || echo -e "\n000")
    fi
  fi
  status=$(echo "$body" | tail -n1)
  body=$(echo "$body" | sed '$d')
  local sep=$'\x1f'
  if [[ "$status" == "$expected_status" ]]; then
    echo "PASS${sep}$name${sep}$status${sep}$request_line${sep}$body" >> "$TMPRESULTS"
    ((PASSED++)) || true
  else
    echo "FAIL${sep}$name${sep}$status (expected $expected_status)${sep}$request_line${sep}$body" >> "$TMPRESULTS"
    ((FAILED++)) || true
  fi
}

mkdir -p "$REPORT_DIR"

echo "Running manual scenarios (BASE=$BASE)..."
echo ""

# --- MANUAL_TESTING_GUIDE: Movies ---
run_test "Scenario 1: List All Movies" "GET" "$BASE/movies" 200
run_test "Scenario 1: List Movies with q=matrix" "GET" "$BASE/movies?q=matrix" 200
run_test "Scenario 2: Search by title (q=matrix)" "GET" "$BASE/movies/search?q=matrix" 200
run_test "Scenario 2: Search by title and year" "GET" "$BASE/movies/search?q=matrix&year=1999" 200
run_test "Scenario 2: Search by actor only" "GET" "$BASE/movies/search?actor=Keanu%20Reeves" 200
run_test "Scenario 2: Search with pagination" "GET" "$BASE/movies/search?q=matrix&page=1&per_page=2" 200
run_test "Scenario 2: Search no results (404)" "GET" "$BASE/movies/search?q=NonexistentMovieXYZ123" 404
run_test "Scenario 3: Get Movie Details (the-matrix-1999)" "GET" "$BASE/movies/the-matrix-1999" 200
run_test "Scenario 4: Bulk Retrieve (GET slugs)" "GET" "$BASE/movies?slugs=the-matrix-1999,inception-2010" 200
run_test "Scenario 4: Bulk with include" "GET" "$BASE/movies?slugs=the-matrix-1999&include=descriptions,people,genres" 200
run_test "Scenario 5: Search disambiguation (bad boys)" "GET" "$BASE/movies/search?q=bad+boys" 200
run_test "Scenario 5: Get movie by slug (bad-boys-ii-2003)" "GET" "$BASE/movies/bad-boys-ii-2003" 200
run_test "Scenario 6: Refresh Movie (POST)" "POST" "$BASE/movies/the-matrix-1999/refresh" 200
run_test "Scenario 7: Movie Collection" "GET" "$BASE/movies/the-matrix-1999/collection" 200
run_test "Scenario 8: Related movies" "GET" "$BASE/movies/the-matrix-1999/related" 200

# --- MANUAL_TEST_PLANS: TC-MOVIE-* ---
run_test "TC-MOVIE-001: List Movies" "GET" "$BASE/movies" 200
run_test "TC-MOVIE-002: Get Movie by Slug" "GET" "$BASE/movies/the-matrix-1999" 200
run_test "TC-MOVIE-003: Search Movies" "GET" "$BASE/movies/search?q=matrix" 200
run_test "TC-MOVIE-004: Bulk Retrieve (POST)" "POST" "$BASE/movies/bulk" 200 "" '{"slugs":["the-matrix-1999","inception-2010"]}'
run_test "TC-MOVIE-005: Compare Movies" "GET" "$BASE/movies/compare?slug1=the-matrix-1999&slug2=inception-2010" 200
run_test "TC-MOVIE-006: Get Related Movies" "GET" "$BASE/movies/the-matrix-1999/related" 200
run_test "TC-MOVIE-007: Get Movie Collection" "GET" "$BASE/movies/the-matrix-1999/collection" 200
run_test "TC-MOVIE-008: Refresh Movie Data" "POST" "$BASE/movies/the-matrix-1999/refresh" 200
run_test "TC-MOVIE-009: Report Movie Issue" "POST" "$BASE/movies/the-matrix-1999/report" 201 "" '{"type":"factual_error","message":"Test report from manual scenarios"}'
run_test "TC-MOVIE-010: Disambiguation (search bad+boys)" "GET" "$BASE/movies/search?q=bad+boys" 200

# --- MANUAL_TESTING_GUIDE: Health ---
run_test "Scenario Health: Health Check" "GET" "$BASE/health" 200

# --- Write report file ---
{
  echo "# Manual scenarios results – $TIMESTAMP"
  echo ""
  echo "| Name | Result | Request | Response (status + excerpt) |"
  echo "|------|--------|---------|-----------------------------|"
  sep=$'\x1f'
  while IFS="$sep" read -r outcome name status_line request resp; do
    result_label="$outcome"
    [[ "$outcome" == "PASS" ]] && result_label="Pass (green)" || result_label="Failed (red)"
    resp_short=$(echo "$resp" | head -c 200)
    resp_short="${resp_short//$'\n'/ }"
    echo "| $name | $result_label | \`$request\` | $status_line — \`${resp_short}...\` |"
  done < "$TMPRESULTS"
  echo ""
  echo "---"
  echo "Passed: $PASSED, Failed: $FAILED"
} > "$REPORT_FILE"

# --- Full report with full response bodies (append) ---
echo "" >> "$REPORT_FILE"
echo "## Full request/response per scenario" >> "$REPORT_FILE"
while IFS="$sep" read -r outcome name status_line request resp; do
  echo "" >> "$REPORT_FILE"
  echo "### $name — $outcome" >> "$REPORT_FILE"
  echo "- **Request:** \`$request\`" >> "$REPORT_FILE"
  echo "- **Response status:** $status_line" >> "$REPORT_FILE"
  echo "- **Response body:**" >> "$REPORT_FILE"
  echo '```json' >> "$REPORT_FILE"
  echo "$resp" >> "$REPORT_FILE"
  echo '```' >> "$REPORT_FILE"
done < "$TMPRESULTS"

# --- Testdox-style summary to stdout ---
echo "--- Testdox-style summary ---"
while IFS="$sep" read -r outcome name rest; do
  if [[ "$outcome" == "PASS" ]]; then
    echo "[ $name ] Pass (green)"
  else
    echo "[ $name ] Failed (red)"
  fi
done < "$TMPRESULTS"
echo ""
echo "Done. Passed: $PASSED, Failed: $FAILED"
echo "Report: $REPORT_FILE"
