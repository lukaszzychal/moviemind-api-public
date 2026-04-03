# MCP Client Configuration Examples

This document provides configuration examples for connecting to MovieMind MCP servers from various MCP clients.

## Overview

MovieMind provides two MCP server configurations:

- **Local DevOps MCP** (`moviemind_devops`) - Direct database access via STDIO, requires local database connection
- **Remote End-User MCP** (`moviemind_remote`) - HTTP SSE connection to Railway, public access

---

## Configuration for Cursor IDE

Add to `~/.cursor/mcp.json` or your project's `.cursor/mcp.json`:

### Option 1: Remote MCP via SSE (Recommended for End-Users)

```json
{
  "mcpServers": {
    "moviemind_remote": {
      "command": "npx",
      "args": [
        "-y",
        "supergateway",
        "--sse",
        "https://mcp-moviemind-api.up.railway.app/sse",
        "--header",
        "Authorization: Bearer YOUR_TOKEN_HERE"
      ]
    }
  }
}
```

**Usage:**

- No local setup required
- Access from anywhere
- Requires authentication token
- Uses public MovieMind API

**How to use in Cursor:**

Argument to the search tool is **`query`** (substring of title), not `title_query`.

```typescript
// Polish descriptions (default locale)
await CallMcpTool({
  server: "user-moviemind_remote",
  toolName: "search_database_movies",
  arguments: { query: "Matrix" },
});

// Latest description for another locale (must match API enum)
await CallMcpTool({
  server: "user-moviemind_remote",
  toolName: "search_database_movies",
  arguments: { query: "Prestige", locale: "en-US" },
});
```

### Option 2: Local DevOps MCP via STDIO (Recommended for Developers)

```json
{
  "mcpServers": {
    "moviemind_devops": {
      "command": "node",
      "args": [
        "/path/to/moviemind-api-public/mcp-server/build/index.js"
      ],
      "env": {
        "DB_HOST": "localhost",
        "DB_PORT": "5434",
        "DB_DATABASE": "moviemind",
        "DB_USERNAME": "moviemind",
        "DB_PASSWORD": "moviemind",
        "LARAVEL_API_URL": "http://localhost:8000/api/v1",
        "LARAVEL_API_KEY": "your-api-key",
        "LARAVEL_ADMIN_TOKEN": "your-admin-token"
      }
    }
  }
}
```

**Usage:**

- Direct database access
- Admin operations (retry failed jobs, clear cache)
- Requires local Docker environment
- Full DevOps capabilities

---

## Configuration for Claude Desktop

Add to Claude Desktop config: on macOS
`~/Library/Application Support/Claude/claude_desktop_config.json`, on Windows
`%APPDATA%\Claude\claude_desktop_config.json`.

### Remote MCP via SSE

```json
{
  "mcpServers": {
    "moviemind_remote": {
      "command": "npx",
      "args": [
        "-y",
        "supergateway",
        "--sse",
        "https://mcp-moviemind-api.up.railway.app/sse",
        "--header",
        "Authorization: Bearer YOUR_TOKEN_HERE"
      ]
    }
  }
}
```

### Local DevOps MCP via STDIO

```json
{
  "mcpServers": {
    "moviemind_devops": {
      "command": "node",
      "args": [
        "/absolute/path/to/mcp-server/build/index.js"
      ],
      "env": {
        "DB_HOST": "localhost",
        "DB_PORT": "5434",
        "DB_DATABASE": "moviemind",
        "DB_USERNAME": "moviemind",
        "DB_PASSWORD": "moviemind",
        "LARAVEL_API_URL": "http://localhost:8000/api/v1",
        "LARAVEL_API_KEY": "your-api-key",
        "LARAVEL_ADMIN_TOKEN": "your-admin-token"
      }
    }
  }
}
```

---

## Configuration for Other MCP Clients

### Generic SSE Connection (Any Client)

Use the `@modelcontextprotocol/sdk` package:

```javascript
const { Client } = require("@modelcontextprotocol/sdk/client/index.js");
const { SSEClientTransport } = require("@modelcontextprotocol/sdk/client/sse.js");

const transport = new SSEClientTransport(
  new URL("https://mcp-moviemind-api.up.railway.app/sse")
);

const client = new Client(
  { name: "my-client", version: "1.0.0" },
  { capabilities: {} }
);

await client.connect(transport);

// Call tools (see "Tool parameters" below for locale and generation)
const result = await client.callTool({
  name: "search_database_movies",
  arguments: { query: "Matrix", locale: "pl-PL" },
});
```

### Generic STDIO Connection (Local)

```javascript
const { Client } = require("@modelcontextprotocol/sdk/client/index.js");
const { StdioClientTransport } = require("@modelcontextprotocol/sdk/client/stdio.js");

const transport = new StdioClientTransport({
  command: "node",
  args: ["/path/to/mcp-server/build/index.js"],
  env: {
    DB_HOST: "localhost",
    DB_PORT: "5434",
    DB_DATABASE: "moviemind",
    DB_USERNAME: "moviemind",
    DB_PASSWORD: "moviemind",
    LARAVEL_API_URL: "http://localhost:8000/api/v1",
    LARAVEL_API_KEY: "your-api-key",
    LARAVEL_ADMIN_TOKEN: "your-admin-token"
  }
});

const client = new Client(
  { name: "my-client", version: "1.0.0" },
  { capabilities: {} }
});

await client.connect(transport);
```

---

## Tool parameters (MovieMind MCP)

### `search_database_movies`

| Argument | Required | Description |
|----------|----------|-------------|
| `query` | yes | Substring matched against movie title (`ILIKE %query%`). |
| `locale` | no | Language for `current_description`. Default `pl-PL`. Allowed: `en-US`, `pl-PL`, `de-DE`, `fr-FR`, `es-ES`. |

`locale` is sent as a bound SQL parameter (`$2`), not concatenated into the query.

Each row may include `current_description`, `context_tag`, `description_created_at`, and
`description_locale` (the locale filter that was applied).

### `generate_ai_description`

| Argument | Required | Description |
|----------|----------|-------------|
| `entity_type` | yes | Use lowercase in MCP: `movie`, `person`, `tv_show`, `tv_series` (API gets uppercase). |
| `slug` or `entity_id` | one of them | Backend slug, e.g. `the-prestige-2006`. |
| `locale` | no | e.g. `pl-PL`, `en-US` (must match API `Locale`). |
| `context_tag` | no | `modern`, `critical`, `humorous` (lowercase), or `DEFAULT`. Wrong casing → **422**. |

After `generate_ai_description`, poll **`check_job_status`** until `DONE`, then **`search_database_movies`**
with the same **`locale`** to read the saved text.

---

## Environment Variables Reference

### Required (for both STDIO and SSE)

| Variable | Description | Example |
|----------|-------------|---------|
| `DB_HOST` | PostgreSQL host | `localhost`, `db` |
| `DB_PORT` | PostgreSQL port | `5432`, `5434` |
| `DB_DATABASE` or `DB_NAME` | Database name | `moviemind`, `moviemind_staging` |
| `DB_USERNAME` or `DB_USER` | Database user | `moviemind`, `sail` |
| `DB_PASSWORD` | Database password | `moviemind` |

### Optional (for API integration)

| Variable | Description | Example |
|----------|-------------|---------|
| `LARAVEL_API_URL` | Laravel API base URL | `http://localhost:8000/api/v1` |
| `LARAVEL_API_KEY` | Public API key | For `generate_ai_description` |
| `LARAVEL_ADMIN_TOKEN` | Admin API token | For `dispatch_job_retry`, `trigger_cache_clear` |
| `DB_SSL` | Enable SSL for DB | `true`, `false` (default: `false`) |

---

## Available Tools

### End-User Tools (available in both local and remote)

- `search_database_movies` — `query` plus optional `locale` (default `pl-PL`)
- `generate_ai_description` — queue AI text; set `locale` and lowercase `context_tag`
- `check_job_status` — poll job by `job_id`

### DevOps Tools (available only in local STDIO)

- `dispatch_job_retry` - Retry all failed queue jobs
- `trigger_cache_clear` - Clear specific cache key or entire cache

### Available Resources

- `moviemind://database/schema` - PostgreSQL schema information
- `moviemind://logs/laravel` - Laravel application logs (last 100 lines)
- `moviemind://i18n/locales-map` - Supported locales mapping

### Available Prompts

- `analyze_failed_generation` - Analyze failed AI generation job
- `audit_translations_and_frontend` - Audit i18n translations

---

## Testing Your Connection

### Test Remote MCP (SSE)

Create `test-remote-mcp.js`:

```javascript
const { Client } = require("@modelcontextprotocol/sdk/client/index.js");
const { SSEClientTransport } = require("@modelcontextprotocol/sdk/client/sse.js");

async function test() {
  const transport = new SSEClientTransport(
    new URL("https://mcp-moviemind-api.up.railway.app/sse")
  );
  const client = new Client(
    { name: "test", version: "1.0.0" },
    { capabilities: {} }
  );
  await client.connect(transport);
  
  const result = await client.callTool({
    name: "search_database_movies",
    arguments: { query: "Matrix" },
  });
  
  console.log(JSON.stringify(result, null, 2));
  await client.close();
}

test().catch(console.error);
```

Run: `node test-remote-mcp.js`

### Test Local MCP (STDIO)

```bash
# Start local Docker environment first
docker compose up -d

# Test via curl (if using SSE locally)
curl http://localhost:3000/sse

# Or use Cursor's CallMcpTool directly
```

---

## Troubleshooting

### "MCP server does not exist"

**Cause:** MCP server is not configured in your client's `mcp.json`.

**Fix:** Add the server configuration to `~/.cursor/mcp.json` (Cursor) or `claude_desktop_config.json` (Claude Desktop).

### "Connection refused" or "Database does not exist"

**Cause:** Environment variables are incorrect or database is not running.

**Fix:**

1. Check Docker: `docker compose ps`
2. Verify env vars match your `.env` file
3. For remote: ensure Railway env vars are set correctly

### "Laravel API Error (422): The entity type must be..."

**Cause:** Remote Railway server is running outdated code.

**Fix:** Redeploy Railway after merging latest PR to `main`.

### "search_database_movies: unsupported locale ..."

**Cause:** `locale` is not one of the allowed API values.

**Fix:** Use exactly `en-US`, `pl-PL`, `de-DE`, `fr-FR`, or `es-ES`, or omit `locale` for default Polish.

### 422 on `context_tag`

**Cause:** Backend expects enum values such as `modern` / `humorous` (lowercase), not `MODERN`.

### "LARAVEL_ADMIN_TOKEN is required"

**Cause:** Trying to use admin tools without token.

**Fix:** Set `LARAVEL_ADMIN_TOKEN` in environment variables.

---

## Security Notes

- **Never commit tokens/passwords to git**
- Use environment variables for sensitive data
- Remote SSE server requires authentication token
- Admin token grants full access - keep it secret
- For production: use HTTPS only

---

## Related Documentation

- [MCP Tutorial](./MCP_TUTORIAL.md) - Understanding MCP concepts
- [MCP Server Course](../pl/MCP_SERVER_COURSE.md) - Building MCP servers
- [MovieMind MCP Analysis](./MOVIEMIND_MCP_ANALYSIS.md) - Architecture
- [Railway Deployment Checklist](./RAILWAY_DEPLOYMENT_CHECKLIST.md) - Deployment guide
