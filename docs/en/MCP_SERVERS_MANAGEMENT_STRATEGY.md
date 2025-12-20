# MCP Servers Management Strategy

## Overview

This document provides a strategy for managing MCP servers in Cursor IDE to optimize performance and stay within the 80-tool limit.

**Current Status:** 84 tools (exceeds limit by 4 tools)

## Server Categories

### 🔴 Core Servers (Always Enabled)

These servers are essential for daily development work and should always be enabled:

| Server | Purpose | Tools Count | Priority |
|--------|---------|-------------|----------|
| **filesystem** | File operations, project management | ~5-10 | Critical |
| **github** | Repository management, issues, PRs | ~15-20 | Critical |
| **sequential-thinking** | Improved AI reasoning, problem-solving | ~1-2 | High |

**Total Core Tools:** ~21-32 tools

### 🟡 Optional Servers (Enable When Needed)

These servers should be enabled only when you're actively using their features:

| Server | Purpose | When to Enable | Tools Count |
|--------|---------|----------------|-------------|
| **postgres** | Database access, queries | When querying database | ~2-3 |
| **Chrome DevTools** | Browser debugging | When debugging web apps | ~10-15 |
| **Railway** | Deployment | When deploying to Railway | ~5-10 |
| **mcp-doc-generator** | Documentation generation | When generating docs | ~3-5 |
| **firecrawl-mcp** | Web scraping | When scraping websites | ~5-10 |
| **memory-bank** | Advanced knowledge storage, RAG, embeddings | When building knowledge base or using RAG | ~3-5 |
| **playwright** | Browser automation | When automating browsers | ~10-15 |
| **notion** | Notion integration | When using Notion for docs | ~10-15 |
| **docker** | Container management | When managing Docker containers | ~8-12 |
| **postman** | API testing | When testing APIs | ~20-30 |

## Recommended Configuration

### Minimal Setup (Always Enabled)

For daily development work, keep only core servers enabled:

```json
{
  "mcpServers": {
    "filesystem": { ... },
    "github": { ... },
    "sequential-thinking": { ... }
  }
}
```

**Estimated Tools:** ~21-32 tools (well below 80 limit)

### Full Setup (When Needed)

Enable additional servers based on your current task:

- **Database Work:** Enable `postgres`
- **Web Development:** Enable `playwright`, `Chrome DevTools`
- **API Development:** Enable `postman`
- **Documentation:** Enable `mcp-doc-generator`, `notion`
- **Deployment:** Enable `Railway`, `docker`
- **Web Scraping:** Enable `firecrawl-mcp`
- **Knowledge Base / RAG:** Enable `memory-bank` (for advanced knowledge storage)

## Quick Enable/Disable Guide

### How to Disable a Server in Cursor

1. Open Cursor Settings → Tools & MCP
2. Find the server in "Installed MCP Servers"
3. Click the toggle to disable it
4. Restart Cursor if needed

### How to Enable a Server

1. Open Cursor Settings → Tools & MCP
2. Find the server in "Installed MCP Servers"
3. Click the toggle to enable it
4. Restart Cursor if needed

## Tool Count Management

### Current Tool Distribution (Estimated)

- Core servers: ~25 tools
- Optional servers: ~59 tools
- **Total:** ~84 tools (exceeds limit)

### Target Configuration

- **Minimal (daily work):** ~25 tools (core only)
- **Database work:** ~28 tools (core + postgres)
- **Web development:** ~50 tools (core + playwright + Chrome DevTools)
- **API development:** ~60 tools (core + postman)
- **Full stack:** ~75 tools (core + multiple optional)

## Best Practices

1. **Start with minimal setup** - Enable only core servers
2. **Enable on demand** - Add servers when you need them
3. **Disable after use** - Turn off servers when done
4. **Monitor tool count** - Check Cursor settings regularly
5. **Group by task** - Enable related servers together

## Server-Specific Notes

### PostgreSQL MCP (DBHub)
- Requires PostgreSQL connection string
- Configured for local database: `postgresql://moviemind:moviemind@localhost:5432/moviemind`
- Enable only when querying database or analyzing schema
- Very token-efficient (~80% reduction in query load)

### Notion MCP
- Requires `NOTION_TOKEN` environment variable
- Get token from: https://www.notion.so/profile/integrations
- Enable only when using Notion for documentation

### Docker MCP
- Requires Docker Desktop or Docker Engine **to be running**
- Enable only when managing containers
- Can be resource-intensive
- **Troubleshooting:** If you see "Failed to connect to Docker daemon" error:
  - Make sure Docker Desktop is running (check Applications or system tray)
  - On macOS, Docker Desktop must be started before using Docker MCP
  - If Docker is not needed, disable Docker MCP server in Cursor settings

### Postman MCP
- Requires `POSTMAN_API_KEY` environment variable
- Get key from: https://postman.postman.co/settings/me/api-keys
- Enable only when testing APIs
- Has multiple modes: minimal, full, code

### Memory Bank MCP
- **What it is:** Advanced knowledge storage system (not the same as AI's regular memory)
- **Regular AI Memory:** Always active - AI remembers context within current chat session
- **Memory Bank MCP:** Optional - stores structured project knowledge (knowledge graphs, embeddings, RAG) in files
- **When to use:**
  - Building a knowledge base for your project
  - Using RAG (Retrieval-Augmented Generation) for better context retrieval
  - Storing project-specific knowledge that should persist across sessions
  - Working with large codebases where you need semantic search
- **When NOT to use:**
  - Simple projects that don't need advanced knowledge storage
  - Regular coding tasks (AI's built-in memory is sufficient)
  - When you want to minimize tool count
- **Note:** Regular AI memory in Cursor is always active and remembers your conversation context. Memory Bank MCP is an additional, advanced feature for structured knowledge storage.

## Troubleshooting

### "Exceeding total tools limit" Warning

**Solution:** Disable optional servers you're not currently using.

**Quick fix:**
1. Disable `postgres` (if not querying database)
2. Disable `Chrome DevTools` (if not debugging)
3. Disable `Railway` (if not deploying)
4. Disable `firecrawl-mcp` (if not scraping)
5. Disable `memory-bank` (if not building knowledge base or using RAG)

### Performance Issues

If Cursor is slow:
1. Check tool count in settings
2. Disable unused servers
3. Restart Cursor
4. Monitor system resources

## Migration Guide

### From Full Setup to Minimal

1. Note which servers you use regularly
2. Disable servers you use less than once per week
3. Keep only core servers enabled
4. Enable others on demand

### From Minimal to Task-Specific

1. Identify your current task (web dev, API testing, etc.)
2. Enable relevant servers for that task
3. Disable when task is complete
4. Return to minimal setup

## References

- [Cursor MCP Documentation](https://cursor.com/docs/mcp)
- [Model Context Protocol Specification](https://spec.modelcontextprotocol.io/)
- [MCP Servers Directory](https://github.com/modelcontextprotocol/servers)

