#!/bin/bash
# Pre-commit hooks installation script for MovieMind API
# This script sets up pre-commit hooks to prevent secrets from being committed

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🔧 Setting up pre-commit hooks for MovieMind API...${NC}"

# Check if we're in a git repository
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    echo -e "${RED}❌ Not in a git repository. Please run this script from the project root.${NC}"
    exit 1
fi

# Check if pre-commit is installed
if ! command -v pre-commit &> /dev/null; then
    echo -e "${YELLOW}⚠️  pre-commit is not installed. Installing...${NC}"
    
    # Try different installation methods
    if command -v pip &> /dev/null; then
        pip install pre-commit
    elif command -v pip3 &> /dev/null; then
        pip3 install pre-commit
    elif command -v brew &> /dev/null; then
        brew install pre-commit
    else
        echo -e "${RED}❌ Cannot install pre-commit automatically. Please install it manually:${NC}"
        echo "  - pip install pre-commit"
        echo "  - brew install pre-commit"
        echo "  - conda install pre-commit"
        exit 1
    fi
fi

# Check if gitleaks is installed
if ! command -v gitleaks &> /dev/null; then
    echo -e "${YELLOW}⚠️  GitLeaks is not installed. Installing...${NC}"
    
    # Detect OS and install gitleaks
    OS=$(uname -s)
    ARCH=$(uname -m)
    
    case $OS in
        "Darwin")
            if command -v brew &> /dev/null; then
                brew install gitleaks
            else
                echo -e "${RED}❌ Please install GitLeaks manually:${NC}"
                echo "  brew install gitleaks"
                exit 1
            fi
            ;;
        "Linux")
            # Download and install gitleaks
            GITLEAKS_VERSION="v8.18.0"
            if [ "$ARCH" = "x86_64" ]; then
                curl -sSfL "https://github.com/gitleaks/gitleaks/releases/download/${GITLEAKS_VERSION}/gitleaks_8.18.0_linux_x64.tar.gz" | tar -xz -C /usr/local/bin
            elif [ "$ARCH" = "arm64" ] || [ "$ARCH" = "aarch64" ]; then
                curl -sSfL "https://github.com/gitleaks/gitleaks/releases/download/${GITLEAKS_VERSION}/gitleaks_8.18.0_linux_arm64.tar.gz" | tar -xz -C /usr/local/bin
            else
                echo -e "${RED}❌ Unsupported architecture: $ARCH${NC}"
                exit 1
            fi
            ;;
        *)
            echo -e "${RED}❌ Unsupported OS: $OS${NC}"
            echo -e "${YELLOW}Please install GitLeaks manually from: https://github.com/gitleaks/gitleaks/releases${NC}"
            exit 1
            ;;
    esac
fi

# Install git hooks from templates
if [ -f "scripts/git-hooks/pre-commit" ]; then
    echo -e "${YELLOW}📦 Installing git hooks from templates...${NC}"
    cp scripts/git-hooks/pre-commit .git/hooks/pre-commit
    chmod +x .git/hooks/pre-commit
    echo -e "${GREEN}✅ Git hooks installed${NC}"
else
    echo -e "${YELLOW}⚠️  Git hooks template not found at scripts/git-hooks/pre-commit${NC}"
fi

# Install pre-commit hooks (if pre-commit framework is used)
if command -v pre-commit &> /dev/null; then
    echo -e "${YELLOW}📦 Installing pre-commit framework hooks...${NC}"
    pre-commit install
    pre-commit install --hook-type pre-commit
    pre-commit install --hook-type pre-push
fi

# Run pre-commit on all files to test
echo -e "${YELLOW}🧪 Testing pre-commit hooks...${NC}"
if pre-commit run --all-files; then
    echo -e "${GREEN}✅ Pre-commit hooks installed and tested successfully!${NC}"
else
    echo -e "${YELLOW}⚠️  Some hooks failed, but this is normal for the first run.${NC}"
    echo -e "${YELLOW}The hooks will now run automatically on each commit.${NC}"
fi

# Create .env.example if it doesn't exist
if [ ! -f ".env.example" ]; then
    echo -e "${YELLOW}📝 Creating .env.example file...${NC}"
    cat > .env.example << 'EOF'
# MovieMind API Environment Variables
# Copy this file to .env and fill in your actual values

# Database Configuration
DATABASE_URL=postgresql://moviemind:moviemind@db:5432/moviemind

# Redis Configuration
REDIS_URL=redis://redis:6379

# OpenAI API Configuration
OPENAI_API_KEY=<REPLACE_ME>

# Application Configuration
APP_ENV=dev
APP_SECRET=<REPLACE_ME>

# API Configuration
API_RATE_LIMIT=1000
API_CACHE_TTL=3600

# Security Configuration
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8080
EOF
    echo -e "${GREEN}✅ Created .env.example file${NC}"
fi

# Update .gitignore to include .env files
if ! grep -q "\.env" .gitignore 2>/dev/null; then
    echo -e "${YELLOW}📝 Updating .gitignore to exclude .env files...${NC}"
    cat >> .gitignore << 'EOF'

# Environment files
.env
.env.local
.env.production
.env.staging
.env.test
EOF
    echo -e "${GREEN}✅ Updated .gitignore${NC}"
fi

echo ""
echo -e "${GREEN}🎉 Pre-commit hooks setup complete!${NC}"
echo ""
echo -e "${BLUE}📋 What happens now:${NC}"
echo -e "  • GitLeaks will scan every commit for secrets"
echo -e "  • Commits will be blocked if secrets are detected"
echo -e "  • Code quality checks will run automatically"
echo -e "  • Large files will be prevented from being committed"
echo ""
echo -e "${BLUE}🔧 Available commands:${NC}"
echo -e "  • pre-commit run --all-files    # Run hooks on all files"
echo -e "  • pre-commit run               # Run hooks on staged files"
echo -e "  • pre-commit clean            # Clean hook cache"
echo -e "  • pre-commit uninstall        # Remove hooks"
echo ""
echo -e "${BLUE}📚 Documentation:${NC}"
echo -e "  • .pre-commit-config.yaml     # Hook configuration"
echo -e "  • .gitleaks.toml              # GitLeaks rules"
echo -e "  • SECURITY.md                 # Security policy"
echo ""
echo -e "${YELLOW}⚠️  Remember: Never commit real API keys or passwords!${NC}"
echo -e "${YELLOW}   Use environment variables and .env files instead.${NC}"
