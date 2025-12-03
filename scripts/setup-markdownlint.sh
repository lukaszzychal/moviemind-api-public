#!/bin/bash
#
# Setup script for Markdownlint
# Installs markdownlint-cli2 via npm (local or global)
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}📝 Setting up Markdownlint...${NC}"

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo -e "${RED}❌ Node.js is not installed.${NC}"
    echo -e "${YELLOW}Please install Node.js first:${NC}"
    echo -e "${YELLOW}  macOS: brew install node${NC}"
    echo -e "${YELLOW}  Linux: https://nodejs.org/${NC}"
    exit 1
fi

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo -e "${RED}❌ npm is not installed.${NC}"
    exit 1
fi

# Prefer local installation (via package.json)
if [ -f "package.json" ]; then
    echo -e "${YELLOW}Installing markdownlint-cli2 via npm (local)...${NC}"
    if npm install; then
        echo -e "${GREEN}✅ Markdownlint-cli2 installed successfully (local)${NC}"
        echo -e "${YELLOW}Usage:${NC}"
        echo -e "${YELLOW}  npm run markdownlint:fix${NC}"
        echo -e "${YELLOW}  npm run markdownlint:check${NC}"
        echo -e "${YELLOW}  npx markdownlint-cli2-fix '**/*.md'${NC}"
    else
        echo -e "${RED}❌ Failed to install markdownlint-cli2${NC}"
        exit 1
    fi
else
    # Fallback to global installation
    echo -e "${YELLOW}Installing markdownlint-cli2 globally...${NC}"
    if npm install -g markdownlint-cli2; then
        echo -e "${GREEN}✅ Markdownlint-cli2 installed successfully (global)${NC}"
        echo -e "${YELLOW}Usage:${NC}"
        echo -e "${YELLOW}  markdownlint-cli2 '**/*.md'${NC}"
        echo -e "${YELLOW}  markdownlint-cli2-fix '**/*.md'${NC}"
    else
        echo -e "${RED}❌ Failed to install markdownlint-cli2${NC}"
        exit 1
    fi
fi

# Check if .markdownlint.json exists
if [ -f ".markdownlint.json" ]; then
    echo -e "${GREEN}✅ Configuration file .markdownlint.json found${NC}"
else
    echo -e "${YELLOW}⚠️  Configuration file .markdownlint.json not found${NC}"
fi

echo -e "${GREEN}✅ Markdownlint setup complete!${NC}"
echo -e "${YELLOW}The pre-commit hook will now automatically format Markdown files.${NC}"

