# Pre-commit Hooks Setup for MovieMind API

## Overview

Pre-commit hooks are scripts that run automatically before each commit to ensure code quality and security. For MovieMind API, we use pre-commit hooks to:

- 🔒 **Detect secrets and API keys** using GitLeaks
- 🧹 **Format code** automatically
- ✅ **Check syntax** for YAML, JSON, and PHP files
- 🚫 **Prevent large files** from being committed
- 🔍 **Scan for merge conflicts**

## Quick Setup

### Automatic Installation

Run the setup script to install everything automatically:

```bash
./scripts/setup-pre-commit.sh
```

This script will:
- Install pre-commit and GitLeaks if not present
- Configure all hooks
- Create `.env.example` file
- Update `.gitignore`
- Test the installation

### Manual Installation

If you prefer to install manually:

1. **Install pre-commit**:
   ```bash
   # Using pip
   pip install pre-commit
   
   # Using brew (macOS)
   brew install pre-commit
   
   # Using conda
   conda install pre-commit
   ```

2. **Install GitLeaks**:
   ```bash
   # macOS
   brew install gitleaks
   
   # Linux
   curl -sSfL https://github.com/gitleaks/gitleaks/releases/download/v8.18.0/gitleaks_8.18.0_linux_x64.tar.gz | tar -xz -C /usr/local/bin
   
   # Windows
   choco install gitleaks
   ```

3. **Install hooks**:
   ```bash
   pre-commit install
   pre-commit install --hook-type pre-commit
   pre-commit install --hook-type pre-push
   ```

## Configuration Files

### `.pre-commit-config.yaml`

Main configuration file that defines all hooks:

```yaml
repos:
  # GitLeaks - Secret detection
  - repo: https://github.com/gitleaks/gitleaks
    rev: v8.18.0
    hooks:
      - id: gitleaks
        name: GitLeaks - Detect secrets and credentials
        entry: gitleaks protect --source . --verbose --redact
        language: system
        stages: [commit, push]

  # Basic file checks
  - repo: https://github.com/pre-commit/pre-commit-hooks
    rev: v4.5.0
    hooks:
      - id: trailing-whitespace
      - id: end-of-file-fixer
      - id: check-yaml
      - id: check-json
      - id: check-merge-conflict
      - id: check-added-large-files
        args: ['--maxkb=1000]

  # PHP specific hooks
  - repo: https://github.com/doctrine/coding-standard
    rev: 12.0.0
    hooks:
      - id: php-cs-fixer
        name: PHP CS Fixer
        entry: php-cs-fixer fix --config=.php-cs-fixer.php
        language: system
        types: [php]
```

### `.gitleaks.toml`

GitLeaks configuration for detecting secrets:

```toml
title = "MovieMind API GitLeaks Configuration"

[extend]
useDefault = true

[extend.rules]
[[extend.rules]]
id = "movie-mind-api-key"
description = "MovieMind API Key"
regex = '''movie_mind_api_key\s*=\s*['"]?[a-zA-Z0-9]{32,}['"]?'''
tags = ["key", "api", "movie-mind"]
entropy = 3.5

[[extend.rules]]
id = "openai-api-key-detailed"
description = "OpenAI API Key (detailed pattern)"
regex = '''sk-[a-zA-Z0-9]{48}'''
tags = ["key", "api", "openai"]
entropy = 4.0
```

## Available Commands

### Running Hooks Manually

```bash
# Run hooks on all files
pre-commit run --all-files

# Run hooks on staged files only
pre-commit run

# Run specific hook
pre-commit run gitleaks

# Run hooks with verbose output
pre-commit run --verbose
```

### Managing Hooks

```bash
# Update hook versions
pre-commit autoupdate

# Clean hook cache
pre-commit clean

# Uninstall hooks
pre-commit uninstall

# Install hooks for specific hook types
pre-commit install --hook-type pre-commit
pre-commit install --hook-type pre-push
```

## Security Features

### Secret Detection

GitLeaks will detect and block commits containing:

- **OpenAI API Keys**: `sk-` followed by 48 characters
- **Database Passwords**: PostgreSQL connection strings
- **Redis Passwords**: Redis connection strings
- **Generic API Keys**: Various API key patterns
- **Environment Variables**: Hardcoded sensitive values

### Example Blocked Patterns

```bash
# These will be blocked:
OPENAI_API_KEY=sk-<REPLACE_ME>
DATABASE_URL=postgresql://user:<REPLACE_ME>@localhost:5432/db
API_KEY="<REPLACE_ME>"
password = "mypassword123"
```

### Allowed Patterns

```bash
# These are allowed:
OPENAI_API_KEY=<REPLACE_ME>
DATABASE_URL=postgresql://moviemind:moviemind@db:5432/moviemind
API_KEY="your-api-key-here"  # Example placeholder
password = "password"        # Default placeholder
```

## Troubleshooting

### Common Issues

1. **GitLeaks not found**:
   ```bash
   # Install GitLeaks
   brew install gitleaks  # macOS
   # or download from GitHub releases
   ```

2. **Pre-commit not found**:
   ```bash
   # Install pre-commit
   pip install pre-commit
   ```

3. **Hooks not running**:
   ```bash
   # Reinstall hooks
   pre-commit uninstall
   pre-commit install
   ```

4. **False positives**:
   - Add patterns to `.gitleaks.toml` allowlist
   - Use placeholder values in examples
   - Add files to `.gitignore`

### Bypassing Hooks (Emergency Only)

⚠️ **Only use in emergencies!**

```bash
# Skip pre-commit hooks (NOT RECOMMENDED)
git commit --no-verify -m "Emergency commit"

# Skip specific hook
SKIP=gitleaks git commit -m "Skip GitLeaks check"
```

## Integration with CI/CD

### GitHub Actions

The pre-commit hooks are also integrated with GitHub Actions:

```yaml
# .github/workflows/security-scan.yml
- name: Run GitLeaks
  uses: gitleaks/gitleaks-action@v2
  env:
    GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
    GITLEAKS_LICENSE: ${{ secrets.GITLEAKS_LICENSE }}
```

### Local Development

Hooks run automatically on:
- `git commit` - Pre-commit hooks
- `git push` - Pre-push hooks
- Manual runs with `pre-commit run`

## Best Practices

### For Developers

1. **Always use environment variables** for secrets
2. **Create `.env.example`** with placeholder values
3. **Never commit real API keys** or passwords
4. **Run hooks before pushing** to avoid CI failures
5. **Update hooks regularly** with `pre-commit autoupdate`

### For Maintainers

1. **Keep hook versions updated**
2. **Monitor hook failures** in CI/CD
3. **Review and update allowlists** regularly
4. **Document any bypass procedures**
5. **Train team on security practices**

## Environment Setup

### Required Files

Create these files in your project root:

```bash
# .env.example (template)
OPENAI_API_KEY=<REPLACE_ME>
DATABASE_URL=postgresql://moviemind:moviemind@db:5432/moviemind
REDIS_URL=redis://redis:6379

# .env (actual values - NOT committed)
OPENAI_API_KEY=sk-<REPLACE_ME>
DATABASE_URL=postgresql://user:<REPLACE_ME>@localhost:5432/moviemind
REDIS_URL=redis://localhost:6379
```

### .gitignore Updates

Ensure these patterns are in `.gitignore`:

```gitignore
# Environment files
.env
.env.local
.env.production
.env.staging
.env.test

# IDE files
.vscode/
.idea/
*.swp
*.swo

# OS files
.DS_Store
Thumbs.db
```

## Support

### Getting Help

- **Issues**: [GitHub Issues](https://github.com/lukaszzychal/moviemind-api-public/issues)
- **Security**: lukasz.zychal.dev@gmail.com
- **Documentation**: See `SECURITY.md` for security policies

### Resources

- [Pre-commit Documentation](https://pre-commit.com/)
- [GitLeaks Documentation](https://github.com/gitleaks/gitleaks)
- [MovieMind Security Policy](SECURITY.md)

---

**Remember**: Pre-commit hooks are your first line of defense against security issues. Always run them and never bypass them unless absolutely necessary!
