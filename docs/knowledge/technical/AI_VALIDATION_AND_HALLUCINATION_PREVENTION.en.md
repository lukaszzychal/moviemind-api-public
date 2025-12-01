# AI Validation and Hallucination Prevention Analysis

> **Created:** 2025-11-30  
> **Context:** Analysis of AI-generated data validation mechanisms and identification of gaps in movie/person existence verification  
> **Category:** technical

## Purpose

Analyzing current AI-generated data validation mechanisms, identifying problems related to generating data for non-existent entities, and proposing solutions.

## Current State

### 1. Slug Format Validation

**Location:** `api/app/Helpers/SlugValidator.php`

**Functionality:**
- `validateMovieSlug()` - checks slug format (length, year, patterns)
- `validatePersonSlug()` - checks slug format (length, word count)
- Returns `confidence` score (0.0-1.0) and `reason`

**Limitations:**
- ✅ Checks only slug format
- ❌ Does not check if movie/person actually exists
- ❌ Does not verify existence in external databases (TMDb, OMDb, etc.)

**Example:**
```php
// Slug "non-existent-movie-test-9999" passes validation
// confidence: 0.6, reason: "Slug looks like a title but no year detected"
```

### 2. AI Prompts

**Location:** `api/app/Services/OpenAiClient.php`

**Current prompts:**

**Movie:**
```php
$systemPrompt = 'You are a movie database assistant. Generate movie information from a slug. Return JSON with: title, release_year, director, description (movie plot), genres (array).';
$userPrompt = "Generate movie information for slug: {$slug}. Return JSON with: title, release_year, director, description (movie plot), genres (array).";
```

**Person:**
```php
$systemPrompt = 'You are a biography assistant. Generate person biography from a slug. Return JSON with: name, birth_date (YYYY-MM-DD), birthplace, biography (full text).';
$userPrompt = "Generate biography for person with slug: {$slug}. Return JSON with: name, birth_date (YYYY-MM-DD), birthplace, biography (full text).";
```

**Problems:**
- ❌ No instructions to verify existence of movie/person
- ❌ No instructions to return error when movie/person does not exist
- ❌ AI may generate data for non-existent movie/person (hallucination)

### 3. Validation of AI-Generated Data

**Location:** `api/app/Jobs/RealGenerateMovieJob.php`, `api/app/Jobs/RealGeneratePersonJob.php`

**Current state:**
- ❌ No validation if title/name from AI response matches slug
- ❌ No validation if release year/birth date are reasonable
- ❌ No mechanism to detect "hallucinations"
- ❌ Data is saved directly to database without verification

**Code example:**
```php
// RealGenerateMovieJob::createMovieRecord()
$title = $aiResponse['title'] ?? Str::of($this->slug)->replace('-', ' ')->title();
$releaseYear = $aiResponse['release_year'] ?? 1999;
// No validation if title matches slug!
```

### 4. Feature Flag `hallucination_guard`

**Location:** `api/app/Features/hallucination_guard.php`

**Status:**
- Feature flag exists but is not used
- No implementation of anti-hallucination mechanisms

## Identified Problems

### Problem 1: No Existence Verification Before Generation

**Description:**
Application does not check if movie/person actually exists before calling AI. For non-existent entities:
- Application returns 202 Accepted and queues generation
- AI attempts to generate data for non-existent movie/person
- AI may "invent" movie/person (hallucination)

**Test example:**
```bash
# Slug for non-existent movie
curl http://localhost:8000/api/v1/movies/non-existent-movie-test-9999
# Returns: 202 Accepted, job_id, status: PENDING
# AI attempts to generate data for non-existent movie
```

**Consequences:**
- Database may contain false data
- Users may receive information about non-existent movies/people
- API costs for unnecessary calls

### Problem 2: No Validation of Data Consistency with Slug

**Description:**
After receiving AI response, application does not verify if:
- Title/name matches slug
- Release year/birth date are reasonable
- Data actually belongs to movie/person specified by slug

**Example:**
```php
// Slug: "the-matrix-1999"
// AI may return: {"title": "Inception", "release_year": 2010}
// Application will save this data without verification!
```

**Consequences:**
- Inconsistent data in database
- Incorrect information for users
- Difficulties in debugging

### Problem 3: AI Prompts Do Not Contain Verification Instructions

**Description:**
Current prompts do not contain instructions to:
- Verify existence of movie/person
- Return error when movie/person does not exist
- Check data consistency with slug

**Consequences:**
- AI may generate data for non-existent entities
- No control over data quality
- High risk of "hallucinations"

## Proposed Solutions

### Solution 1: Existence Verification Before Generation

#### Option A: Integration with TMDb/OMDb API (Recommended for production)

**Implementation:**
1. Before calling AI, check if movie/person exists in TMDb/OMDb
2. If it does not exist, return 404 with message "Movie/Person not found"
3. If it exists, continue with generation

**Advantages:**
- ✅ High accuracy
- ✅ Access to metadata (year, director, cast)
- ✅ Ability to verify before generation

**Disadvantages:**
- ❌ Dependency on external API
- ❌ API call costs
- ❌ Rate limits
- ❌ Requires API keys

**Implementation:**
```php
// New service: ExternalMovieValidationService
class ExternalMovieValidationService
{
    public function movieExists(string $slug): bool
    {
        // Check in TMDb/OMDb
        // Return true/false
    }
}
```

#### Option B: Enhanced Prompts with Verification Instructions (Recommended as first step)

**Implementation:**
1. Add existence verification instruction to prompts
2. AI should return `{"error": "Movie not found"}` when movie does not exist
3. Handle error response in application

**Advantages:**
- ✅ Simple to implement
- ✅ Does not require external APIs
- ✅ Utilizes AI knowledge

**Disadvantages:**
- ❌ AI may still generate data (hallucination)
- ❌ Less accurate than external API
- ❌ Token costs for verification

**Implementation:**
```php
// Enhanced prompt
$systemPrompt = 'You are a movie database assistant. First, verify if the movie exists. If it does not exist, return {"error": "Movie not found"}. If it exists, generate movie information from a slug. Return JSON with: title, release_year, director, description (movie plot), genres (array).';
```

#### Option C: Combination Approach (Recommended long-term)

**Implementation:**
1. **Level 1:** Enhanced prompts with verification instructions (quick deployment)
2. **Level 2:** Data validation heuristics (year, format, slug similarity)
3. **Level 3:** Optional TMDb integration for high confidence (feature flag)
4. **Level 4:** Logging and monitoring of suspicious cases

**Advantages:**
- ✅ Multi-layered protection
- ✅ Ability to deploy gradually
- ✅ Flexibility (can disable external API)

### Solution 2: Validation of Data Consistency with Slug

#### Option A: Validation Heuristics (Recommended)

**Implementation:**
1. Check if release year is reasonable (1888-current year+2)
2. Check if birth date is reasonable
3. Check similarity of slug vs title/name (Levenshtein, fuzzy matching)
4. Reject data if inconsistency > threshold

**Advantages:**
- ✅ Fast, low cost
- ✅ Detects basic inconsistencies
- ✅ Can adjust threshold

**Disadvantages:**
- ❌ May reject correct data (different name formats)
- ❌ Less accurate than AI verification

**Implementation:**
```php
class AiDataValidator
{
    public function validateMovieData(array $aiResponse, string $slug): array
    {
        $errors = [];
        
        // Year validation
        if (isset($aiResponse['release_year'])) {
            $year = (int) $aiResponse['release_year'];
            if ($year < 1888 || $year > date('Y') + 2) {
                $errors[] = 'Invalid release year';
            }
        }
        
        // Title similarity validation
        if (isset($aiResponse['title'])) {
            $similarity = $this->calculateSimilarity($slug, $aiResponse['title']);
            if ($similarity < 0.5) {
                $errors[] = 'Title does not match slug';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
```

#### Option B: Verification via Second AI Call

**Implementation:**
1. After generating data, use AI to verify
2. Prompt: "Verify if this movie/person matches slug: {slug}"
3. Reject data if AI returns inconsistency

**Advantages:**
- ✅ High accuracy
- ✅ Utilizes AI context

**Disadvantages:**
- ❌ Double API costs
- ❌ Slower processing

### Solution 3: Activate Feature Flag `hallucination_guard`

**Implementation:**
1. Implement anti-hallucination mechanisms
2. Activate feature flag `hallucination_guard`
3. Use flag to control validation on/off

**Advantages:**
- ✅ Ability to deploy gradually
- ✅ Ability to disable in case of problems
- ✅ Flexibility

## Recommendations

### Short-term (1-2 weeks)

1. **Enhanced prompts** - add existence verification instruction
2. **Validation heuristics** - basic year and slug similarity validation
3. **Error handling** - return 404 when AI returns "not found"

### Medium-term (1-2 months)

1. **Feature flag `hallucination_guard`** - implementation and activation
2. **Extended heuristics** - more advanced validation
3. **Logging and monitoring** - track suspicious cases

### Long-term (3-6 months)

1. **TMDb/OMDb integration** - optional verification before generation
2. **Machine learning** - hallucination detection model
3. **Dashboard** - AI data quality monitoring

## Tests

### Scenario 1: Non-existent Movie

**Test:**
```bash
curl http://localhost:8000/api/v1/movies/non-existent-movie-xyz-9999
```

**Expected result (after implementation):**
- 404 Not Found with message "Movie not found"
- OR 202 Accepted but job ends with error "Movie not found"

**Current result:**
- 202 Accepted, job queues generation
- AI attempts to generate data

### Scenario 2: Inconsistent AI Data

**Test:**
```bash
# Slug: "the-matrix-1999"
# AI returns: {"title": "Inception", "release_year": 2010}
```

**Expected result (after implementation):**
- Data is rejected
- Job ends with error "Data validation failed"
- Log contains inconsistency details

**Current result:**
- Data is saved without verification

## Related Documents

- [Manual Testing Guide](../reference/MANUAL_TESTING_GUIDE.md)
- [OpenAI Integration](../tutorials/OPENAI_SETUP_AND_TESTING.md)
- [Task TASK-037](../../issue/en/TASKS.md#task-037)
- [Task TASK-038](../../issue/en/TASKS.md#task-038)

## Notes

- Feature flag `hallucination_guard` exists but is not used
- Manual tests showed that AI attempts to generate data for non-existent entities
- OpenAI API returns 400 error for some non-existent slugs (probably request format issue)

---

**Last updated:** 2025-11-30

