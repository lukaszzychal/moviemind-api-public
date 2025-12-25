# GitGuardian False Positives - Handling Guide

## Overview

This document explains how to handle false positives detected by GitGuardian in the MovieMind API project.

## Common False Positives

### 1. Test Database Passwords in CI Workflows

**Problem:** GitGuardian detects test database passwords in GitHub Actions workflows as "Generic Password" secrets.

**Why it's a false positive:**
- These are test-only passwords for CI environments
- They are not production secrets
- They use dynamic values based on `github.run_id` and `github.run_number`
- They are clearly marked with `ggignore` comments

**Example:**
```yaml
# ggignore: This is a test database password, not a real secret
POSTGRES_PASSWORD: ${{ env.POSTGRES_TEST_KEY }}
```

**Location in code:**
- `.github/workflows/ci.yml` - `test-postgresql` job
- `.github/workflows/ci.yml` - `postman-tests` job

## How to Mark as False Positive

### Method 1: GitGuardian Dashboard (Recommended)

1. **Access GitGuardian Dashboard**
   - Go to [https://dashboard.gitguardian.com/](https://dashboard.gitguardian.com/)
   - Log in with your account

2. **Navigate to Incidents**
   - Click on **"Incidents"** or **"Secrets"** in the navigation menu
   - Filter by repository: `lukaszzychal/moviemind-api-public`
   - Filter by PR: `#183` (or relevant PR number)

3. **Find the Detection**
   - Look for detections with:
     - Type: "Generic Password"
     - File: `.github/workflows/ci.yml`
     - Commits: `2383575`, `d33b9e9`, or similar

4. **Mark as False Positive**
   - Click on the detection to open details
   - Click **"Mark as False Positive"** or **"Ignore"** button
   - Add a comment: "Test database password for CI only, not a production secret"
   - Select reason: "Test/Development Environment"

5. **Verify**
   - The detection should now be marked as "False Positive"
   - It should not appear in future scans

### Method 2: GitHub Integration (If GitGuardian GitHub App is installed)

1. **Go to Pull Request**
   - Navigate to the PR on GitHub (e.g., PR #183)
   - Scroll to the **"GitGuardian Security Checks"** section

2. **View Detection**
   - Click on **"View secret"** link next to the detection
   - This opens GitGuardian interface

3. **Mark as False Positive**
   - In the GitGuardian interface, click **"Mark as False Positive"**
   - Add a comment explaining why it's a false positive
   - Save the change

### Method 3: GitGuardian API (Advanced)

If you have GitGuardian API access, you can mark false positives programmatically:

```bash
# Get incident ID from GitGuardian dashboard
INCIDENT_ID="your-incident-id"
API_KEY="your-gitguardian-api-key"

curl -X PATCH \
  "https://api.gitguardian.com/v1/incidents/${INCIDENT_ID}/resolve" \
  -H "Authorization: Token ${API_KEY}" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "false_positive",
    "comment": "Test database password for CI only, not a production secret"
  }'
```

## Prevention

To prevent false positives in the future:

1. **Use Environment Variables**
   - Use `env.POSTGRES_TEST_KEY` instead of direct values
   - Use dynamic values based on `github.run_id`

2. **Add `ggignore` Comments**
   - Add `# ggignore: This is a test database password, not a real secret` comments
   - Add explanatory comments: `# GitGuardian: This is a test database password for CI only`

3. **Use GitHub Secrets (Alternative)**
   - For more sensitive test passwords, use GitHub Secrets
   - Access via `${{ secrets.POSTGRES_TEST_PASSWORD }}`
   - Requires configuration in GitHub repository settings

## Current Status

### PR #183 - CI Improvements
- ✅ **Fixed in code:** All test passwords use `POSTGRES_TEST_KEY` with `ggignore` comments
- ⚠️ **Requires manual action:** Old commits (`2383575`, `d33b9e9`) need to be marked as false positive in GitGuardian UI

## Related Files

- `.github/workflows/ci.yml` - GitHub Actions workflows
- `docs/GITGUARDIAN_FALSE_POSITIVES.md` - This document

## References

- [GitGuardian Documentation](https://docs.gitguardian.com/)
- [GitGuardian Dashboard](https://dashboard.gitguardian.com/)
- [GitGuardian API Documentation](https://api.gitguardian.com/docs)

---

**Last Updated:** 2025-12-25  
**Maintained by:** AI Agent
