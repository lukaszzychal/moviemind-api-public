/**
 * MovieMind API Manual Testing Script (Node.js)
 * 
 * Usage:
 *   node test-api.js [base_url]
 * 
 * Example:
 *   node test-api.js http://localhost:8000
 * 
 * Requirements:
 *   npm install node-fetch (or use native fetch in Node 18+)
 */

const BASE_URL = process.argv[2] || 'http://localhost:8000';
const API_URL = `${BASE_URL}/api/v1`;

// Colors for console output
const colors = {
    reset: '\x1b[0m',
    green: '\x1b[32m',
    blue: '\x1b[34m',
    yellow: '\x1b[33m',
    red: '\x1b[31m',
};

// Helper function to make requests
async function makeRequest(method, endpoint, data = null, description = '') {
    console.log(`${colors.yellow}→ ${description}${colors.reset}`);
    console.log(`  ${method} ${endpoint}`);

    try {
        const options = {
            method,
            headers: {
                'Accept': 'application/json',
                ...(data && { 'Content-Type': 'application/json' }),
            },
            ...(data && { body: JSON.stringify(data) }),
        };

        const response = await fetch(`${API_URL}${endpoint}`, options);
        const body = await response.json();

        const statusColor = response.status >= 200 && response.status < 300
            ? colors.green
            : response.status >= 300 && response.status < 400
            ? colors.yellow
            : colors.red;

        console.log(`  ${statusColor}✓ Status: ${response.status}${colors.reset}`);
        console.log(JSON.stringify(body, null, 2));
        console.log('');

        return { status: response.status, body };
    } catch (error) {
        console.error(`${colors.red}✗ Error: ${error.message}${colors.reset}\n`);
        return null;
    }
}

// Main test function
async function runTests() {
    console.log(`${colors.blue}=== MovieMind API Manual Testing ===${colors.reset}\n`);
    console.log(`Base URL: ${BASE_URL}\n`);

    // 1. Health Check
    console.log(`${colors.blue}=== Health Check ===${colors.reset}`);
    await makeRequest('GET', '/health/openai', null, 'OpenAI Health Check');

    // 2. List Movies
    console.log(`${colors.blue}=== Movies ===${colors.reset}`);
    await makeRequest('GET', '/movies', null, 'List all movies');
    await makeRequest('GET', '/movies?q=Matrix', null, "Search movies: 'Matrix'");

    // 3. Advanced Search
    console.log(`${colors.blue}=== Advanced Search ===${colors.reset}`);
    await makeRequest('GET', '/movies/search?q=Matrix', null, 'Search: Matrix');
    await makeRequest('GET', '/movies/search?q=Matrix&year=1999', null, 'Search: Matrix (year 1999)');
    await makeRequest('GET', '/movies/search?q=Matrix&director=Wachowski', null, 'Search: Matrix (director Wachowski)');
    await makeRequest('GET', '/movies/search?q=Matrix&page=1&per_page=5', null, 'Search: Matrix (paginated)');

    // 4. Get Movie
    console.log(`${colors.blue}=== Get Movie ===${colors.reset}`);
    await makeRequest('GET', '/movies/the-matrix-1999', null, 'Get movie: the-matrix-1999');

    // 5. Generate Description
    console.log(`${colors.blue}=== Generation ===${colors.reset}`);
    const generateResult = await makeRequest('POST', '/generate', {
        entity_type: 'MOVIE',
        slug: 'the-matrix-1999',
        locale: 'en-US',
        context_tag: 'DEFAULT',
    }, 'Generate description: the-matrix-1999');

    // Extract job_id and check status
    if (generateResult && generateResult.body.job_id) {
        console.log(`${colors.blue}=== Job Status ===${colors.reset}`);
        await makeRequest('GET', `/jobs/${generateResult.body.job_id}`, null, 
            `Get job status: ${generateResult.body.job_id}`);
    }

    // 6. Disambiguation Example
    console.log(`${colors.blue}=== Disambiguation ===${colors.reset}`);
    await makeRequest('GET', '/movies/bad-boys', null, 
        'Get movie: bad-boys (may trigger disambiguation)');
    await makeRequest('GET', '/movies/bad-boys?slug=bad-boys-ii-2003', null, 
        'Select from disambiguation: bad-boys (slug=bad-boys-ii-2003)');

    // 7. People
    console.log(`${colors.blue}=== People ===${colors.reset}`);
    await makeRequest('GET', '/people', null, 'List all people');
    await makeRequest('GET', '/people?q=Keanu', null, "Search people: 'Keanu'");

    console.log(`${colors.green}=== Testing Complete ===${colors.reset}\n`);
    console.log('Note: Some endpoints may return 202 (queued) or 300 (disambiguation)');
    console.log(`Check job status with: GET ${API_URL}/jobs/{job_id}`);
    console.log('');

    // API Endpoints Reference
    printEndpointsReference();
}

// Print API Endpoints Reference
function printEndpointsReference() {
    console.log(`${colors.blue}════════════════════════════════════════════════════════════════${colors.reset}`);
    console.log(`${colors.blue}=== API Endpoints Reference ===${colors.reset}`);
    console.log(`${colors.blue}════════════════════════════════════════════════════════════════${colors.reset}`);
    console.log('');

    // Movies
    console.log(`${colors.yellow}📽️  MOVIES${colors.reset}`);
    console.log(`${colors.green}GET${colors.reset}  ${API_URL}/movies`);
    console.log('  Description: List all movies (with optional ?q=search query)');
    console.log(`  Example: ${API_URL}/movies?q=Matrix`);
    console.log('');

    console.log(`${colors.green}GET${colors.reset}  ${API_URL}/movies/search`);
    console.log('  Description: Advanced movie search (local + TMDb)');
    console.log('  Query params: ?q=title&year=1999&director=Name&actor[]=Name1&actor[]=Name2&page=1&per_page=20');
    console.log(`  Example: ${API_URL}/movies/search?q=Matrix&year=1999&page=1&per_page=10`);
    console.log('');

    console.log(`${colors.green}GET${colors.reset}  ${API_URL}/movies/{slug}`);
    console.log('  Description: Get movie by slug (may return disambiguation if ambiguous)');
    console.log('  Query params: ?description_id=123 (optional)');
    console.log(`  Example: ${API_URL}/movies/the-matrix-1999`);
    console.log(`  Example: ${API_URL}/movies/matrix?slug=the-matrix-1999 (select from disambiguation)`);
    console.log('');

    console.log(`${colors.green}POST${colors.reset} ${API_URL}/movies/{slug}/refresh`);
    console.log('  Description: Refresh movie metadata from TMDb');
    console.log(`  Example: curl -X POST ${API_URL}/movies/the-matrix-1999/refresh`);
    console.log('');

    // People
    console.log(`${colors.yellow}👤 PEOPLE${colors.reset}`);
    console.log(`${colors.green}GET${colors.reset}  ${API_URL}/people`);
    console.log('  Description: List all people (with optional ?q=search query)');
    console.log(`  Example: ${API_URL}/people?q=Keanu`);
    console.log('');

    console.log(`${colors.green}GET${colors.reset}  ${API_URL}/people/{slug}`);
    console.log('  Description: Get person by slug');
    console.log('  Query params: ?bio_id=123 (optional)');
    console.log(`  Example: ${API_URL}/people/keanu-reeves`);
    console.log('');

    console.log(`${colors.green}POST${colors.reset} ${API_URL}/people/{slug}/refresh`);
    console.log('  Description: Refresh person metadata from TMDb');
    console.log(`  Example: curl -X POST ${API_URL}/people/keanu-reeves/refresh`);
    console.log('');

    // Generation
    console.log(`${colors.yellow}🤖 GENERATION${colors.reset}`);
    console.log(`${colors.green}POST${colors.reset} ${API_URL}/generate`);
    console.log('  Description: Queue AI description/bio generation');
    console.log('  Required body: {"entity_type":"MOVIE","slug":"the-matrix-1999"}');
    console.log('  Optional body fields: "locale":"en-US", "context_tag":"DEFAULT"');
    console.log('  entity_type: MOVIE | ACTOR | PERSON');
    console.log('  context_tag: DEFAULT | modern | critical | humorous');
    console.log(`  Example: curl -X POST ${API_URL}/generate -H 'Content-Type: application/json' -d '{"entity_type":"MOVIE","slug":"the-matrix-1999","locale":"en-US","context_tag":"DEFAULT"}'`);
    console.log('');

    // Jobs
    console.log(`${colors.yellow}📋 JOBS${colors.reset}`);
    console.log(`${colors.green}GET${colors.reset}  ${API_URL}/jobs/{id}`);
    console.log('  Description: Get generation job status');
    console.log(`  Example: ${API_URL}/jobs/7bec7007-7e93-4db5-afe4-0a96c490a16d`);
    console.log('');

    // Health
    console.log(`${colors.yellow}🏥 HEALTH${colors.reset}`);
    console.log(`${colors.green}GET${colors.reset}  ${API_URL}/health/openai`);
    console.log('  Description: Check OpenAI API connectivity');
    console.log(`  Example: ${API_URL}/health/openai`);
    console.log('');

    // Admin (with authentication)
    console.log(`${colors.yellow}🔐 ADMIN (requires Basic Auth)${colors.reset}`);
    console.log(`${colors.green}GET${colors.reset}  ${API_URL}/admin/flags`);
    console.log('  Description: List all feature flags');
    console.log(`  Example: curl -u admin:password ${API_URL}/admin/flags`);
    console.log('');

    console.log(`${colors.green}POST${colors.reset} ${API_URL}/admin/flags/{name}`);
    console.log('  Description: Set feature flag state');
    console.log('  Body: {"state":"on"} or {"state":"off"}');
    console.log(`  Example: curl -u admin:password -X POST ${API_URL}/admin/flags/ai_description_generation -H 'Content-Type: application/json' -d '{"state":"on"}'`);
    console.log('');

    console.log(`${colors.green}GET${colors.reset}  ${API_URL}/admin/flags/usage`);
    console.log('  Description: Get feature flags usage statistics');
    console.log(`  Example: curl -u admin:password ${API_URL}/admin/flags/usage`);
    console.log('');

    console.log(`${colors.green}GET${colors.reset}  ${API_URL}/admin/debug/config`);
    console.log('  Description: Get debug configuration (development only)');
    console.log(`  Example: curl -u admin:password ${API_URL}/admin/debug/config`);
    console.log('');

    console.log(`${colors.blue}════════════════════════════════════════════════════════════════${colors.reset}`);
    console.log('');
    console.log(`${colors.yellow}Response Codes:${colors.reset}`);
    console.log('  200 - Success');
    console.log('  202 - Generation queued (check job status)');
    console.log('  300 - Disambiguation required (multiple matches)');
    console.log('  404 - Not found');
    console.log('  422 - Validation error');
    console.log('  500 - Server error');
    console.log('');
}

// Run tests
runTests().catch(console.error);

