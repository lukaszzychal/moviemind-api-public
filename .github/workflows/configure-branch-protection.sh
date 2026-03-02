#!/bin/bash
# Script to configure branch protection rules via GitHub API
# This prevents merging PRs with failed status checks

set -e

REPO="lukaszzychal/moviemind-api-public"
BRANCH="main"

echo "Configuring branch protection for $REPO:$BRANCH..."

# Check if gh CLI is installed
if ! command -v gh &> /dev/null; then
    echo "Error: GitHub CLI (gh) is not installed"
    echo "Install it from: https://cli.github.com/"
    exit 1
fi

# Configure branch protection
gh api repos/$REPO/branches/$BRANCH/protection \
  --method PUT \
  --field required_status_checks='{"strict":true,"contexts":["Test PHP 8.2","Test PHP 8.3","Security & Lint","Postman API Tests"]}' \
  --field enforce_admins=true \
  --field required_pull_request_reviews='{"required_approving_review_count":1,"dismiss_stale_reviews":true}' \
  --field restrictions=null \
  --field allow_force_pushes=false \
  --field allow_deletions=false \
  --field required_linear_history=true

echo "✅ Branch protection configured successfully!"
echo ""
echo "Required status checks:"
echo "  - Test PHP 8.2"
echo "  - Test PHP 8.3"
echo "  - Security & Lint"
echo "  - Postman API Tests (Newman)"
echo ""
echo "PRs cannot be merged until all checks pass."

