# BDD HTML Documentation - Explanation

## What is Automatic HTML Documentation?

When using BDD frameworks like **Behat** (PHP) or **Cucumber** (other languages), you can automatically generate beautiful HTML documentation from your Gherkin feature files.

## How It Works

### 1. Write Scenarios in Gherkin (Natural Language)

```gherkin
Feature: Movie Metadata Synchronization
  As a system administrator
  I want movies to automatically sync actors and crew from TMDB
  So that the database stays up-to-date with cast information

  Scenario: Movie creation triggers metadata sync
    Given a movie is created from TMDB data
    When the movie is saved
    Then SyncMovieMetadataJob should be dispatched
    And the job should sync actors and crew from TMDB
```

### 2. Generate HTML Documentation

```bash
vendor/bin/behat --format html --out docs/features.html
```

### 3. Result: Beautiful HTML Page

The generated HTML contains:

- **Feature descriptions** - What each feature does
- **All scenarios** - Every test case written in natural language
- **Status indicators** - Which scenarios pass/fail
- **Step definitions** - Links to implementation code
- **Examples** - Data tables and examples
- **Tags** - Categorization (e.g., `@api`, `@critical`)

## Example Generated HTML Structure

```html
<!DOCTYPE html>
<html>
<head>
    <title>MovieMind API - Living Documentation</title>
    <style>
        /* Beautiful styling */
        .feature { background: #f5f5f5; padding: 20px; }
        .scenario { border-left: 4px solid #4CAF50; padding: 10px; }
        .passed { color: green; }
        .failed { color: red; }
    </style>
</head>
<body>
    <h1>MovieMind API - Living Documentation</h1>
    
    <div class="feature">
        <h2>Feature: Movie Metadata Synchronization</h2>
        <p>As a system administrator, I want movies to automatically sync...</p>
        
        <div class="scenario passed">
            <h3>Scenario: Movie creation triggers metadata sync</h3>
            <ul>
                <li class="passed">Given a movie is created from TMDB data</li>
                <li class="passed">When the movie is saved</li>
                <li class="passed">Then SyncMovieMetadataJob should be dispatched</li>
                <li class="passed">And the job should sync actors and crew</li>
            </ul>
        </div>
    </div>
</body>
</html>
```

## Benefits for Clients/Stakeholders

### 1. **No Technical Knowledge Required**

Clients can read scenarios in plain English:
- "When a user requests a movie description"
- "Then the system should return AI-generated content"

### 2. **Always Up-to-Date**

- Documentation is generated from **executable tests**
- If a test fails, documentation shows it
- No manual documentation updates needed

### 3. **Visual Status Dashboard**

- Green = Feature works ✅
- Red = Feature broken ❌
- Yellow = Feature in progress 🟡

### 4. **Contract Definition**

- Scenarios define **exactly** what the system does
- Clients can verify behavior matches expectations
- Serves as acceptance criteria

## Real-World Example

### Before (Technical Documentation)

```
API Endpoint: POST /api/v1/generate
Parameters:
  - entity_type: string (required)
  - entity_id: string (required)
  - locale: string (optional)
  - context_tag: string (optional)
Response: 202 Accepted with job_id
```

### After (BDD HTML Documentation)

```gherkin
Feature: AI Description Generation
  As an API consumer
  I want to generate AI descriptions for movies
  So that I can get unique content

  Scenario: Generate description for new movie
    Given I am an API consumer
    When I POST to "/api/v1/generate" with:
      | entity_type | MOVIE        |
      | entity_id   | the-matrix   |
      | locale      | pl-PL        |
      | context_tag | modern       |
    Then I should receive status 202 Accepted
    And the response should contain job_id
    And a generation job should be queued
```

**Generated HTML shows:**
- ✅ What the feature does (plain English)
- ✅ Step-by-step scenario
- ✅ Example data
- ✅ Expected outcomes
- ✅ Current status (pass/fail)

## When Would You Need This?

### ✅ Good Use Cases:

1. **Client-Facing API Documentation**
   - Clients need to understand API behavior
   - Non-technical stakeholders need to verify features
   - Contract definition between teams

2. **Large Teams**
   - Product Owners write scenarios
   - Developers implement
   - QA verifies
   - All read the same documentation

3. **Compliance/Regulatory**
   - Need auditable documentation
   - Must prove system behavior
   - Requirements traceability

### ❌ Not Needed For:

1. **Small Teams** (like MovieMind API)
   - You already understand the code
   - Feature Tests are readable enough
   - Extra overhead not worth it

2. **Internal APIs**
   - Only developers use it
   - Technical documentation is sufficient
   - No client-facing requirements

3. **Rapid Development**
   - BDD slows down initial development
   - Feature Tests are faster to write
   - Can add BDD later if needed

## Comparison: BDD HTML vs Feature Tests

| Aspect | BDD HTML Docs | Feature Tests |
|--------|---------------|---------------|
| **Readability (non-tech)** | ⭐⭐⭐⭐⭐ | ⭐⭐ |
| **Speed of writing** | ⭐⭐ | ⭐⭐⭐⭐ |
| **Maintenance** | ⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Client-friendly** | ⭐⭐⭐⭐⭐ | ⭐⭐ |
| **Developer-friendly** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |

## Conclusion

**BDD HTML Documentation is valuable when:**
- You have non-technical stakeholders who need to understand system behavior
- You need contract-style documentation for clients
- You want living documentation that's always up-to-date

**For MovieMind API:**
- Feature Tests are sufficient
- No client-facing documentation needed
- Team is small and technical
- Can add BDD later if requirements change

---

**Last Updated:** 2025-12-18

