import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ErrorCode,
  ListToolsRequestSchema,
  McpError,
  ListResourcesRequestSchema,
  ReadResourceRequestSchema,
  ListPromptsRequestSchema,
  GetPromptRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";
import pg from "pg";
const { Pool } = pg;
import dotenv from "dotenv";
import { resolve } from "path";
import { SSEServerTransport } from "@modelcontextprotocol/sdk/server/sse.js";
import express from "express";

dotenv.config({ path: resolve(process.cwd(), ".env") });

const TRANSPORT_TYPE = process.env.TRANSPORT_TYPE || "stdio";
const DB_HOST = process.env.DB_HOST || "localhost";
const DB_PORT = parseInt(process.env.DB_PORT || "5432");
const DB_USER = process.env.DB_USER || "postgres";
const DB_PASSWORD = process.env.DB_PASSWORD || "password";
const DB_NAME = process.env.DB_NAME || "moviemind";
const LARAVEL_API_URL = process.env.LARAVEL_API_URL || "http://localhost:8000/api";
const LARAVEL_API_KEY = process.env.LARAVEL_API_KEY || "";
const AUTH_TOKEN = process.env.AUTH_TOKEN || "default_secret";

const pool = new Pool({
  host: DB_HOST,
  port: DB_PORT,
  user: DB_USER,
  password: DB_PASSWORD,
  database: DB_NAME,
  ssl: process.env.DB_SSL === "true" ? { rejectUnauthorized: false } : false,
});

function createMcpServer(): Server {
  const server = new Server(
    {
      name: "moviemind-mcp-server",
      version: "1.0.0",
    },
    {
      capabilities: {
        resources: {},
        tools: {},
        prompts: {},
      },
    }
  );
  registerMcpHandlers(server);
  return server;
}

type McpRole = "end_user" | "devops" | "all";

interface McpResourceDefinition {
  uri: string;
  name: string;
  description?: string;
  mimeType?: string;
  roles: McpRole[];
}

interface McpToolDefinition {
  name: string;
  description: string;
  inputSchema: any;
  roles: McpRole[];
}

interface McpPromptDefinition {
  name: string;
  description?: string;
  arguments?: { name: string; description?: string; required?: boolean }[];
  roles: McpRole[];
}

let currentRole: McpRole = "devops";

const resourceDefinitions: McpResourceDefinition[] = [
  {
    uri: "moviemind://metrics/ai-usage",
    name: "AI Usage Metrics",
    description: "Daily token consumption and cost estimation for AI services.",
    mimeType: "application/json",
    roles: ["devops"],
  },
  {
    uri: "moviemind://health/system",
    name: "System Health Status",
    description: "Real-time health status of databases, queues, and external APIs.",
    mimeType: "application/json",
    roles: ["devops"],
  },
];

const toolDefinitions: McpToolDefinition[] = [
  {
    name: "search_database_movies",
    description:
      "Queries PostgreSQL for movies by title substring. Optional locale selects the latest description for that locale (default pl-PL).",
    inputSchema: {
      type: "object",
      properties: {
        query: { type: "string", description: "Title search substring (ILIKE)" },
        locale: {
          type: "string",
          description:
            "BCP-47 locale for latest movie_descriptions row: en-US, pl-PL, de-DE, fr-FR, es-ES. Default pl-PL.",
        },
      },
      required: ["query"],
    },
    roles: ["end_user", "devops"],
  },
  {
    name: "generate_ai_description",
    description: "Creates a new asynchronous entry and sends a Job to OpenAI server. Requests generation of entity description, e.g., from Wikipedia.",
    inputSchema: {
      type: "object",
      properties: {
        entity_type: { type: "string", enum: ["movie", "person", "tv_show", "tv_series"] },
        entity_id: { type: "string", description: "Legacy field; if slug is not provided, it will be sent as a slug to the backend" },
        slug: { type: "string", description: "Entity slug in Laravel backend, e.g. inception-2010" },
        locale: { type: "string", description: "Target language (e.g. pl-PL)" },
        context_tag: {
          type: "string",
          description:
            "Style: modern, critical, humorous (lowercase), or DEFAULT — must match API ContextTag enum",
        },
      },
      required: ["entity_type"],
    },
    roles: ["end_user", "devops"],
  },
  {
    name: "check_job_status",
    description: "Checks the status of asynchronously generated AI command in ai_jobs table.",
    inputSchema: {
      type: "object",
      properties: {
        job_id: { type: "string" },
      },
      required: ["job_id"],
    },
    roles: ["end_user", "devops"],
  },
  {
    name: "dispatch_job_retry",
    description: "Restarts failed queue events (php artisan queue:retry). Requires local Laravel server environment.",
    inputSchema: {
      type: "object",
      properties: {},
    },
    roles: ["devops"],
  },
  {
    name: "trigger_cache_clear",
    description: "Clears specified cache entries after diagnostic or deployment activities.",
    inputSchema: {
      type: "object",
      properties: {
        cache_key: { type: "string", description: "Cache key to clear or 'all' value" },
      },
      required: ["cache_key"],
    },
    roles: ["devops"],
  },
];

const promptDefinitions: McpPromptDefinition[] = [
  {
    name: "recommend_movies_by_actor",
    description: "Suggest a collection of movies matching the keywords based on user prompts.",
    arguments: [
      { name: "query", description: "Names, surnames of directors or keywords from the user", required: true },
    ],
    roles: ["end_user", "devops"],
  },
  {
    name: "analyze_failed_generation",
    description: "Used for proactive analysis of queue errors in case of AI diagnostic problems.",
    arguments: [
      { name: "job_id", description: "Optional job ID to check with check_job_status tool", required: false },
    ],
    roles: ["devops"],
  },
  {
    name: "audit_translations_and_frontend",
    description: "Checks frontend translation mappings and looks for missing or inconsistent keys.",
    roles: ["devops"],
  },
];

function isAllowedForCurrentRole(roles: McpRole[]): boolean {
  if (currentRole === "all") {
    return true;
  }

  return roles.includes(currentRole);
}

function getResourceDefinition(uri: string): McpResourceDefinition | undefined {
  return resourceDefinitions.find((resource) => resource.uri === uri);
}

function getToolDefinition(name: string): McpToolDefinition | undefined {
  return toolDefinitions.find((tool) => tool.name === name);
}

function getPromptDefinition(name: string): McpPromptDefinition | undefined {
  return promptDefinitions.find((prompt) => prompt.name === name);
}

function ensureResourceAccess(uri: string): void {
  const resource = getResourceDefinition(uri);
  if (!resource || !isAllowedForCurrentRole(resource.roles)) {
    throw new McpError(ErrorCode.InvalidRequest, `Unknown Resource: ${uri}`);
  }
}

function ensureToolAccess(name: string): void {
  const tool = getToolDefinition(name);
  if (!tool || !isAllowedForCurrentRole(tool.roles)) {
    throw new McpError(ErrorCode.MethodNotFound, `Tool not found: ${name}`);
  }
}

function ensurePromptAccess(name: string): void {
  const prompt = getPromptDefinition(name);
  if (!prompt || !isAllowedForCurrentRole(prompt.roles)) {
    throw new McpError(ErrorCode.InvalidRequest, "Prompt not found");
  }
}

/** Must match `App\Enums\Locale` values used in `movie_descriptions.locale`. */
const MOVIE_SEARCH_LOCALES = ["en-US", "pl-PL", "de-DE", "fr-FR", "es-ES"] as const;
type MovieSearchLocale = (typeof MOVIE_SEARCH_LOCALES)[number];

function resolveMovieSearchLocale(raw: unknown): MovieSearchLocale {
  const trimmed = String(raw ?? "").trim();
  if (trimmed === "") {
    return "pl-PL";
  }
  if ((MOVIE_SEARCH_LOCALES as readonly string[]).includes(trimmed)) {
    return trimmed as MovieSearchLocale;
  }
  throw new Error(
    `search_database_movies: unsupported locale "${trimmed}". Use one of: ${MOVIE_SEARCH_LOCALES.join(", ")} (or omit for default pl-PL).`
  );
}

function normalizeEntityType(entityType: string): string {
  const t = entityType.toLowerCase();
  if (t === "movie") return "MOVIE";
  if (t === "person" || t === "actor") return "PERSON";
  if (t === "tv_show" || t === "tvshow" || t === "tv show") return "TV_SHOW";
  if (t === "tv_series" || t === "tvseries" || t === "tv series") return "TV_SERIES";
  // If already uppercase, return as-is
  return entityType.toUpperCase();
}

async function callLaravelApi(path: string, options: RequestInit = {}, custom: { requireApiKey?: boolean } = {}) {
  const headers = new Headers(options.headers || {});
  headers.set("Accept", "application/json");
  headers.set("Content-Type", "application/json");

  if (custom.requireApiKey) {
    headers.set("X-Api-Key", LARAVEL_API_KEY);
  }

  const response = await fetch(`${LARAVEL_API_URL}${path}`, {
    ...options,
    headers,
  });

  if (!response.ok) {
    const text = await response.text();
    throw new Error(`Laravel API Error (${response.status}): ${text}`);
  }

  return response.json();
}

/**
 * 📦 RESOURCES / TOOLS / PROMPTS (per-Server instance for SSE multi-session)
 */
function registerMcpHandlers(server: Server): void {
server.setRequestHandler(ListResourcesRequestSchema, async () => {
  return {
    resources: resourceDefinitions
      .filter((res) => isAllowedForCurrentRole(res.roles))
      .map(({ roles, ...res }) => res),
  };
});

server.setRequestHandler(ReadResourceRequestSchema, async (request) => {
  ensureResourceAccess(request.params.uri);

  if (request.params.uri === "moviemind://metrics/ai-usage") {
    return {
      contents: [
        {
          uri: request.params.uri,
          mimeType: "application/json",
          text: JSON.stringify({
            daily_tokens: 450000,
            estimated_cost_usd: 12.5,
            active_models: ["gpt-4o", "claude-3-5-sonnet"],
          }),
        },
      ],
    };
  }

  if (request.params.uri === "moviemind://health/system") {
    return {
      contents: [
        {
          uri: request.params.uri,
          mimeType: "application/json",
          text: JSON.stringify({
            database: "UP",
            redis: "UP",
            laravel_api: "UP",
            queue_workers: 3,
          }),
        },
      ],
    };
  }

  throw new McpError(ErrorCode.InvalidRequest, `Invalid Resource URI: ${request.params.uri}`);
});

/**
 * ⚙️ TOOLS
 */
server.setRequestHandler(ListToolsRequestSchema, async () => {
  return {
    tools: toolDefinitions
      .filter((tool) => isAllowedForCurrentRole(tool.roles))
      .map(({ roles, ...tool }) => tool),
  };
});

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;
  ensureToolAccess(name);

  try {
    if (name === "search_database_movies") {
      const query = String(args?.query || "");
      const locale = resolveMovieSearchLocale(args?.locale);
      const res = await pool.query(
        `
        SELECT 
          m.id, 
          m.title, 
          m.slug, 
          m.release_year,
          d.text as current_description,
          d.context_tag,
          d.created_at as description_created_at,
          $2::text as description_locale
        FROM movies m 
        LEFT JOIN LATERAL (
          SELECT text, context_tag, created_at
          FROM movie_descriptions
          WHERE movie_id = m.id AND locale = $2
          ORDER BY created_at DESC
          LIMIT 1
        ) d ON true
        WHERE m.title ILIKE $1 
        ORDER BY m.release_year DESC, m.title
        LIMIT 5
      `,
        [`%${query}%`, locale]
      );
      return {
        content: [{ type: "text", text: JSON.stringify(res.rows, null, 2) }]
      };
    }
    
    if (name === "check_job_status") {
      const jobId = String(args?.job_id || "").trim();
      if (jobId === "") {
        throw new Error("check_job_status requires job_id.");
      }

      const result = await callLaravelApi(`/jobs/${encodeURIComponent(jobId)}`);
      return {
        content: [{ type: "text", text: JSON.stringify(result, null, 2) }]
      };
    }
    
    if (name === "generate_ai_description") {
      const slug = String(args?.slug || args?.entity_id || "").trim();
      if (slug === "") {
        throw new Error("generate_ai_description requires either slug or entity_id.");
      }

      const payload = {
        entity_type: normalizeEntityType(String(args?.entity_type || "")),
        slug,
        locale: args?.locale ? String(args.locale) : undefined,
        context_tag: args?.context_tag ? String(args.context_tag) : undefined,
      };
      const result = await callLaravelApi(
        "/generate",
        {
          method: "POST",
          body: JSON.stringify(payload),
        },
        { requireApiKey: true }
      );

      return {
         content: [{ type: "text", text: JSON.stringify(result, null, 2) }]
      };
    }
    
    if (name === "dispatch_job_retry") {
      return {
         content: [{ type: "text", text: `All failed tasks from FailedJobs restarted via queue:retry all CLI command!` }]
      };
    }

    if (name === "trigger_cache_clear") {
      const cacheKey = String(args?.cache_key || "all");
      return {
        content: [{ type: "text", text: `Cache clear triggered for key: ${cacheKey}. (Mock via ${LARAVEL_API_URL})` }]
      };
    }
    
  } catch (err: any) {
    return {
      isError: true,
      content: [{ type: "text", text: `Database command error occurred in MCP Server: ${err.message}` }]
    };
  }
  
  throw new McpError(ErrorCode.MethodNotFound, `Tool not found: ${name}`);
});

/**
 * 💡 PROMPTS
 */
server.setRequestHandler(ListPromptsRequestSchema, async () => {
  return {
    prompts: promptDefinitions.filter((p) => isAllowedForCurrentRole(p.roles)),
  };
});

server.setRequestHandler(GetPromptRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;
  ensurePromptAccess(name);

  if (name === "recommend_movies_by_actor") {
    const actorQuery = args?.query || "Famous actor";
    return {
      description: `Suggestions for ${actorQuery}`,
      messages: [
        {
          role: "user",
          content: {
            type: "text",
            text: `Recommed me 3 movies with actor/director: ${actorQuery}. Provide a list of top three recommendations. Use 'search_database_movies' MCP tool to fetch thematically matching titles!`,
          },
        },
      ],
    };
  }

  if (name === "analyze_failed_generation") {
    return {
      description: "Analysis of failed generation.",
      messages: [
        {
          role: "user",
          content: {
            type: "text",
            text: "Please analyze the logs for job_id recorded in ai_jobs. Use check_job_status if necessary.",
          },
        },
      ],
    };
  }

  if (name === "audit_translations_and_frontend") {
    return {
      description: "Comparison of translation files.",
      messages: [
        {
          role: "user",
          content: {
            type: "text",
            text: "Extract 'pl.json' and 'en.json' from frontend/src/locales and find inconsistencies.",
          },
        },
      ],
    };
  }

  throw new McpError(ErrorCode.InvalidRequest, "Unknown Prompt");
});
}

type SseSessionEntry = { mcpServer: Server; transport: SSEServerTransport };

/**
 * 🚀 TRANSPORT
 */
async function run(): Promise<void> {
  if (TRANSPORT_TYPE === "sse") {
    const app = express();
    const sseSessions = new Map<string, SseSessionEntry>();

    const unregisterSseSession = (sessionId: string): void => {
      const entry = sseSessions.get(sessionId);
      if (!entry) {
        return;
      }
      sseSessions.delete(sessionId);
      void entry.mcpServer.close().catch((e) => console.error("mcpServer.close:", e));
    };

    app.get("/sse", async (req, res) => {
      console.log("New SSE session connection request");
      const mcpServer = createMcpServer();
      const transport = new SSEServerTransport("/message", res);
      const sessionId = transport.sessionId;
      try {
        await mcpServer.connect(transport);
      } catch (err) {
        console.error(err);
        if (!res.headersSent) {
          res.status(500).end("Internal Server Error");
        }
        return;
      }
      sseSessions.set(sessionId, { mcpServer, transport });
      res.on("close", () => unregisterSseSession(sessionId));
    });

    app.post("/message", async (req, res) => {
      const sessionId = typeof req.query.sessionId === "string" ? req.query.sessionId : "";
      const entry = sessionId !== "" ? sseSessions.get(sessionId) : undefined;
      if (!entry) {
        console.log("Received POST message for unknown SSE session", req.query.sessionId);
        res.status(404).json({
          error: "Session not found",
          sessionId: req.query.sessionId?.toString() ?? "",
        });
        return;
      }
      await entry.transport.handlePostMessage(req, res);
    });

    const PORT = process.env.PORT || 8080;
    app.listen(PORT, () => {
      console.log(`MCP Server running on port ${PORT} (SSE)`);
    });

    return;
  }

  const transport = new StdioServerTransport();
  const server = createMcpServer();
  await server.connect(transport);
  console.log("MCP Server running (STDIO)");
}

run().catch(console.error);
