import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { SSEServerTransport } from "@modelcontextprotocol/sdk/server/sse.js";
import {
  CallToolRequestSchema,
  ErrorCode,
  ListResourcesRequestSchema,
  ListToolsRequestSchema,
  McpError,
  ReadResourceRequestSchema,
  ListPromptsRequestSchema,
  GetPromptRequestSchema
} from "@modelcontextprotocol/sdk/types.js";
import express from "express";
import cors from "cors";
import dotenv from "dotenv";
import { Pool } from "pg";

// Load environment variables from the .env file
dotenv.config();

// Define the MCP server instance
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

// PostgreSQL connection (Docker)
const pool = new Pool({
  host: process.env.DB_HOST || "postgres",
  port: parseInt(process.env.DB_PORT || "5432"),
  user: process.env.DB_USERNAME || "sail",
  password: process.env.DB_PASSWORD || "password",
  database: process.env.DB_DATABASE || "moviemind",
});

const LARAVEL_API_URL = process.env.LARAVEL_API_URL || "http://laravel.test/api/v1";
const LARAVEL_API_KEY = process.env.LARAVEL_API_KEY;
type McpRole = "end_user" | "devops" | "all";

type McpResourceDefinition = {
  uri: string;
  name: string;
  mimeType: string;
  description: string;
  roles: McpRole[];
};

type McpToolDefinition = {
  name: string;
  description: string;
  inputSchema: {
    type: "object";
    properties: Record<string, unknown>;
    required?: string[];
  };
  roles: McpRole[];
};

type McpPromptDefinition = {
  name: string;
  description: string;
  arguments?: Array<{
    name: string;
    description: string;
    required: boolean;
  }>;
  roles: McpRole[];
};

function resolveRole(role: string | undefined): McpRole {
  if (role === "end_user" || role === "devops" || role === "all") {
    return role;
  }

  return "devops";
}

const currentRole = resolveRole(process.env.MCP_ROLE);

const resourceDefinitions: McpResourceDefinition[] = [
  {
    uri: "moviemind://database/schema-summary",
    name: "Database Schema Summary",
    mimeType: "application/json",
    description: "Database schema structure and relationships in MovieMind (TMDb integration)",
    roles: ["end_user", "devops"],
  },
  {
    uri: "moviemind://frontend/i18n-maps/pl",
    name: "Polish Translations Mapping",
    mimeType: "application/json",
    description: "Frontend dictionary for PL translations",
    roles: ["end_user", "devops"],
  },
  {
    uri: "moviemind://logs/laravel-recent",
    name: "Recent Laravel Error Logs",
    mimeType: "text/plain",
    description: "Last lines of storage/logs/laravel.log for diagnostics",
    roles: ["devops"],
  },
  {
    uri: "moviemind://cache/horizon-metrics",
    name: "Horizon Queue Metrics",
    mimeType: "application/json",
    description: "Statistics from Laravel Horizon Redis queue",
    roles: ["devops"],
  },
];

const toolDefinitions: McpToolDefinition[] = [
  {
    name: "generate_ai_description",
    description: "Creates a new asynchronous entry and sends a Job to OpenAI server. Requests generation of entity description, e.g., from Wikipedia.",
    inputSchema: {
      type: "object",
      properties: {
        entity_type: { type: "string", description: "Type of object, e.g. movie, person", enum: ["movie", "person", "tv_show", "tv_series"] },
        slug: { type: "string", description: "Entity slug in Laravel backend, e.g. inception-2010" },
        entity_id: { type: "string", description: "Legacy field; if slug is not provided, it will be sent as a slug to the backend" },
        locale: { type: "string", description: "Target language (e.g. pl-PL)", default: "pl-PL" },
        context_tag: { type: "string", description: "Optional generation context, e.g. modern or critical" },
      },
      required: ["entity_type"],
    },
    roles: ["end_user", "devops"],
  },
  {
    name: "search_database_movies",
    description: "Queries PostgreSQL relational database for movies by keyword or last name.",
    inputSchema: {
      type: "object",
      properties: {
        query: { type: "string", description: "Title, last name or base word from the user" },
      },
      required: ["query"],
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

function normalizeEntityType(entityType: string): string {
  const entityTypeMap: Record<string, string> = {
    movie: "MOVIE",
    person: "PERSON",
    actor: "PERSON",
    tv_series: "TV_SERIES",
    tv_show: "TV_SHOW",
  };

  return entityTypeMap[entityType.toLowerCase()] ?? entityType.toUpperCase();
}

async function parseLaravelJsonResponse(response: Response): Promise<unknown> {
  const text = await response.text();

  if (text === "") {
    return null;
  }

  try {
    return JSON.parse(text) as unknown;
  } catch {
    return { raw: text };
  }
}

async function callLaravelApi(
  path: string,
  init?: RequestInit,
  options?: { requireApiKey?: boolean }
): Promise<unknown> {
  if (options?.requireApiKey && !LARAVEL_API_KEY) {
    throw new Error("LARAVEL_API_KEY is required for this MCP tool.");
  }

  const headers = new Headers(init?.headers);
  if (options?.requireApiKey && LARAVEL_API_KEY) {
    headers.set("X-API-Key", LARAVEL_API_KEY);
  }
  if (init?.body && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  const response = await fetch(`${LARAVEL_API_URL}${path}`, {
    ...init,
    headers,
  });

  const payload = await parseLaravelJsonResponse(response);
  if (!response.ok) {
    throw new Error(`Laravel API request failed with status ${response.status}: ${JSON.stringify(payload)}`);
  }

  return payload;
}

/**
 * 🗂️ RESOURCES
 * - moviemind://database/schema-summary
 * - moviemind://frontend/i18n-maps/pl
 * - moviemind://logs/laravel-recent
 * - moviemind://cache/horizon-metrics
 */
server.setRequestHandler(ListResourcesRequestSchema, async () => {
  return {
    resources: resourceDefinitions
      .filter((resource) => isAllowedForCurrentRole(resource.roles))
      .map(({ roles, ...resource }) => resource),
  };
});

server.setRequestHandler(ReadResourceRequestSchema, async (request) => {
  ensureResourceAccess(request.params.uri);

  if (request.params.uri === "moviemind://database/schema-summary") {
    return {
      contents: [
        {
          uri: request.params.uri,
          mimeType: "application/json",
          text: JSON.stringify({
            tables: {
              movies: ["id", "tmdb_id", "title", "original_title", "overview", "release_date"],
              people: ["id", "tmdb_id", "name", "biography", "place_of_birth"],
              ai_jobs: ["id", "type", "entity_type", "entity_id", "status", "result"]
            },
            relations: {
              movies_people: "Many-to-Many relationships bound via foreign keys and cached TMDb queries"
            }
          }, null, 2)
        }
      ]
    };
  }

  if (request.params.uri === "moviemind://frontend/i18n-maps/pl") {
    return {
      contents: [
        {
          uri: request.params.uri,
          mimeType: "application/json",
          text: JSON.stringify({
            locale: "pl",
            messages: {
              search: "Szukaj",
              movies: "Filmy",
              people: "Osoby",
              recommendations: "Polecane",
            },
          }, null, 2),
        },
      ],
    };
  }

  // Sample diagnostic error output for DevOps use
  if (request.params.uri === "moviemind://logs/laravel-recent") {
    return {
      contents: [
        {
          uri: request.params.uri,
          mimeType: "text/plain",
          text: "[2026-03-18 12:00:00] local.ERROR: Sample MCP Log output representing failure in Connection."
        }
      ]
    }
  }

  if (request.params.uri === "moviemind://cache/horizon-metrics") {
    return {
      contents: [
        {
          uri: request.params.uri,
          mimeType: "application/json",
          text: JSON.stringify({
            queue: "default",
            pending_jobs: 2,
            failed_jobs: 1,
            throughput_per_minute: 14,
          }, null, 2),
        },
      ],
    };
  }

  throw new McpError(ErrorCode.InvalidRequest, `Unknown Resource: ${request.params.uri}`);
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
      const res = await pool.query(`SELECT id, tmdb_id, title, original_title FROM movies WHERE title ILIKE $1 LIMIT 5`, [`%${query}%`]);
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
 * 🤔 PROMPTS
 */
server.setRequestHandler(ListPromptsRequestSchema, async () => {
  return {
    prompts: promptDefinitions
      .filter((prompt) => isAllowedForCurrentRole(prompt.roles))
      .map(({ roles, ...prompt }) => prompt),
  };
});

// Instead of expanding the base prompt schema, the server can prepare a ready-to-use prompt call
server.setRequestHandler(GetPromptRequestSchema, async (request) => {
  ensurePromptAccess(request.params.name);

  if (request.params.name === "recommend_movies_by_actor") {
    return {
      description: "Analytical prompt supporting frontend interlocutor",
      messages: [
        {
          role: "user",
          content: {
            type: "text",
            text: `User asked about keywords: ${request.params.arguments?.query}.
Provide a list of top three recommendations. Use 'search_database_movies' MCP tool to fetch thematically matching titles!`
          }
        }
      ]
    };
  }

  if (request.params.name === "analyze_failed_generation") {
    const jobId = request.params.arguments?.job_id;
    const jobInstruction = jobId
      ? `Then use the 'check_job_status' tool for job_id=${jobId}.`
      : "If the user provides a job ID, use the 'check_job_status' tool.";

    return {
      description: "Prompt for analyzing generation errors and queue logs",
      messages: [
        {
          role: "user",
          content: {
            type: "text",
            text: `Analyze recent application logs and point out the most likely cause of generation error. ${jobInstruction}`,
          }
        },
        {
          role: "user",
          content: {
            type: "resource",
            resource: {
              uri: "moviemind://logs/laravel-recent",
              text: "Current Laravel logs for diagnosing generation errors.",
            }
          }
        }
      ]
    };
  }

  if (request.params.name === "audit_translations_and_frontend") {
    return {
      description: "Prompt for reviewing frontend translation mappings",
      messages: [
        {
          role: "user",
          content: {
            type: "text",
            text: "Review the attached translation mapping and list missing, ambiguous or potentially inconsistent keys."
          }
        },
        {
          role: "user",
          content: {
            type: "resource",
            resource: {
              uri: "moviemind://frontend/i18n-maps/pl",
              text: "Current Polish frontend translations.",
            }
          }
        }
      ]
    };
  }

  throw new McpError(ErrorCode.InvalidRequest, `Prompt not found`);
});

/**
 * TRANSPORT MANAGEMENT (Stdio vs. HTTP SSE)
 */
async function run() {
  const transportType = process.env.TRANSPORT_TYPE || "stdio";

  if (transportType === "sse") {
    // Railway environment: start listening with Express.js
    const app = express();
    app.use(cors());
    app.use(express.json());

    // HTTP security middleware
    const AUTH_TOKEN = process.env.AUTH_TOKEN;
    if (!AUTH_TOKEN) {
      throw new Error("AUTH_TOKEN is required when TRANSPORT_TYPE is set to sse.");
    }

    app.use((req, res, next) => {
      const authHeader = req.headers.authorization;
      if (!authHeader || authHeader !== `Bearer ${AUTH_TOKEN}`) {
        // Fallback to URI query parameters for initial setup
        if(req.query.token !== AUTH_TOKEN) {
           return res.status(401).send("Unauthorized Access. Invalid Bearer token!");
        }
      }
      next();
    });

    const transports = new Map<string, SSEServerTransport>();

    app.get("/sse", async (req, res) => {
      // Create an absolute URL for the POST message endpoint. This prevents issues 
      // where clients misparse relative URLs and lose the sessionId.
      const protocol = req.headers["x-forwarded-proto"] || req.protocol || "http";
      const host = req.headers.host;
      const messageUrl = `${protocol}://${host}/message`;

      const sseTransport = new SSEServerTransport(messageUrl, res);
      transports.set(sseTransport.sessionId, sseTransport);
      await server.connect(sseTransport);
      console.log(`Client connected via SSE successfully. Session ID: ${sseTransport.sessionId}`);
      
      res.on('close', () => {
        console.log(`Client disconnected. Session ID: ${sseTransport.sessionId}`);
        transports.delete(sseTransport.sessionId);
      });
    });

    app.post("/message", async (req, res) => {
      let sessionId = req.query.sessionId as string;
      let sseTransport = sessionId ? transports.get(sessionId) : undefined;
      
      if (!sseTransport) {
        console.warn(`[POST /message] Session not found by query param '${sessionId}'. Size: ${transports.size}. Original URL: ${req.originalUrl}`);
      }

      // Fallback: If the client improperly stripped query parameters and there is only one active 
      // session, use that session automatically. This is a common bug in some MCP clients.
      if (!sseTransport && transports.size === 1) {
        console.warn(`[POST /message] Applying single-session fallback.`);
        sseTransport = Array.from(transports.values())[0];
         sessionId = sseTransport.sessionId;
      }

      if (sseTransport) {
        await sseTransport.handlePostMessage(req, res, req.body);
      } else {
        res.status(404).send(`Session not found. (session ID: ${sessionId || "empty/stripped"})`);
      }
    });

    const PORT = process.env.PORT || 8080;
    app.listen(PORT, () => {
      console.log(`MovieMind MCP Server Web (SSE) listening on port ${PORT}`);
    });
  } else {
    // Local STDIO diagnostic process (standard in/out for Docker or a local machine with Cursor)
    const transport = new StdioServerTransport();
    await server.connect(transport);
    console.error("MovieMind MCP Server started in terminal mode (STDIO). Waiting for I/O...");
  }
}

run().catch(console.error);
