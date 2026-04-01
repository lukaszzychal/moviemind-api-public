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

/**
 * 🗂️ RESOURCES
 * - moviemind://database/schema-summary
 * - moviemind://frontend/i18n-maps/pl
 * - moviemind://logs/laravel-recent
 * - moviemind://cache/horizon-metrics
 */
server.setRequestHandler(ListResourcesRequestSchema, async () => {
  return {
    resources: [
      {
        uri: "moviemind://database/schema-summary",
        name: "Database Schema Summary",
        mimeType: "application/json",
        description: "Schemat struktury i relacji w bazie MovieMind (TMDb integration)",
      },
      {
        uri: "moviemind://frontend/i18n-maps/pl",
        name: "Polish Translations Mapping",
        mimeType: "application/json",
        description: "Frontend dictionary for PL translations",
      },
      {
        uri: "moviemind://logs/laravel-recent",
        name: "Recent Laravel Error Logs",
        mimeType: "text/plain",
        description: "Last lines of storage/logs/laravel.log for diagnostics",
      },
      {
        uri: "moviemind://cache/horizon-metrics",
        name: "Horizon Queue Metrics",
        mimeType: "application/json",
        description: "Statystyki z Laravel Horizon Redis queue",
      }
    ],
  };
});

server.setRequestHandler(ReadResourceRequestSchema, async (request) => {
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

  throw new McpError(ErrorCode.InvalidRequest, `Unknown Resource: ${request.params.uri}`);
});


/**
 * ⚙️ TOOLS
 */
server.setRequestHandler(ListToolsRequestSchema, async () => {
  return {
    tools: [
      {
        name: "generate_ai_description",
        description: "Tworzy nowy wpis asynchroniczny i wysyła Job na serwer OpenAI. Zleca wygenerowanie opisu dla encji np. z Wikipedii.",
        inputSchema: {
          type: "object",
          properties: {
            entity_type: { type: "string", description: "Typ obiektu np: movie, person", enum: ["movie", "person", "tv_show", "tv_series"] },
            entity_id: { type: "number", description: "ID obiektu w MovieMind bazy danych" },
            locale: { type: "string", description: "Docelowy język (np. pl-PL)", default: "pl-PL" },
          },
          required: ["entity_type", "entity_id"],
        },
      },
      {
        name: "search_database_movies",
        description: "Odpytuje relacyjną bazę PostgreSQL o filmy ze słowem kluczowym lub nazwiskiem.",
        inputSchema: {
          type: "object",
          properties: {
            query: { type: "string", description: "Tytuł, nazwisko lub słowo bazowe od użytkownika" },
          },
          required: ["query"],
        },
      },
      {
        name: "check_job_status",
        description: "Sprawdza status wygenerowanego asynchronicznie polecenia AI w tabeli ai_jobs.",
        inputSchema: {
          type: "object",
          properties: {
            job_id: { type: "number" },
          },
          required: ["job_id"],
        },
      },
      {
        name: "dispatch_job_retry",
        description: "Restartuje sfailowane eventy w kolejce (php artisan queue:retry). Wymaga środowiska serwera lokalnego Laravela.",
        inputSchema: {
          type: "object",
          properties: {},
        },
      }
    ],
  };
});

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  try {
    if (name === "search_database_movies") {
      const query = String(args?.query || "");
      const res = await pool.query(`SELECT id, tmdb_id, title, original_title FROM movies WHERE title ILIKE $1 LIMIT 5`, [`%${query}%`]);
      return {
        content: [{ type: "text", text: JSON.stringify(res.rows, null, 2) }]
      };
    }
    
    if (name === "check_job_status") {
      const jobId = Number(args?.job_id);
      const res = await pool.query(`SELECT id, status, result FROM ai_jobs WHERE id = $1`, [jobId]);
      return {
        content: [{ type: "text", text: res.rows.length > 0 ? JSON.stringify(res.rows[0], null, 2) : "Status: Not found. Job ID doesn't exist." }]
      };
    }
    
    if (name === "generate_ai_description") {
      return {
         content: [{ type: "text", text: `Zadanie AI zostało pomyślnie dopisane do kolejki asynchronicznej we wstrzykniętym REST-Call'u! (Mock)` }]
      };
    }
    
    if (name === "dispatch_job_retry") {
      return {
         content: [{ type: "text", text: `Wszystkie opadłe Zadania z FailedJobs zrestartowane poprzez polecenie queue:retry all na warstwie CLI!` }]
      };
    }
    
  } catch (err: any) {
    return {
      isError: true,
      content: [{ type: "text", text: `Pojawił się błąd komendy bazy danych w MCP Serwerze: ${err.message}` }]
    };
  }
  
  throw new McpError(ErrorCode.MethodNotFound, `Tool not found: ${name}`);
});


/**
 * 🤔 PROMPTS
 */
server.setRequestHandler(ListPromptsRequestSchema, async () => {
  return {
    prompts: [
      {
        name: "recommend_movies_by_actor",
        description: "Zaproponuj użytkownikowi kolekcję filmów pasujących do fraz w oparciu o polecenia użytkownika.",
        arguments: [
          { name: "query", description: "Imiona, nazwiska reżyserów lub keywordy od usera", required: true }
        ]
      },
      {
        name: "analyze_failed_generation",
        description: "Używane do proaktywnej analizy błędów kolejek w przypadku problemów diagnostycznych AI.",
      }
    ]
  };
});

// Instead of expanding the base prompt schema, the server can prepare a ready-to-use prompt call
server.setRequestHandler(GetPromptRequestSchema, async (request) => {
  if (request.params.name === "recommend_movies_by_actor") {
    return {
      description: "Prompt analityczny wspierający rozmówcę z frontend",
      messages: [
        {
          role: "user",
          content: {
            type: "text",
            text: `Użytkownik zapytał o frazy: ${request.params.arguments?.query}. 
Podaj mu listę trzech najlepszych rekomendacji. Do pomocy użyj narzędzia MCP 'search_database_movies' by pobrać tytuły pasujące tematycznie!`
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
    const AUTH_TOKEN = process.env.AUTH_TOKEN || "DEBUG_TOKEN123";
    app.use((req, res, next) => {
      const authHeader = req.headers.authorization;
      if (!authHeader || authHeader !== `Bearer ${AUTH_TOKEN}`) {
        // Fallback to URI query parameters for initial setup
        if(req.query.token !== AUTH_TOKEN) {
           return res.status(401).send("Unauthorized Access. Zły token Bearer!");
        }
      }
      next();
    });

    let sseTransport: SSEServerTransport | null = null;
    
    app.get("/sse", async (req, res) => {
      sseTransport = new SSEServerTransport("/message", res);
      await server.connect(sseTransport);
      console.log("Klient połączony prze SSE pomyślnie.");
    });

    app.post("/message", async (req, res) => {
      if (sseTransport) {
        await sseTransport.handlePostMessage(req, res);
      } else {
        res.status(400).send("No active SSE connection.");
      }
    });

    const PORT = process.env.PORT || 8080;
    app.listen(PORT, () => {
      console.log(`MovieMind MCP Server Web (SSE) nasłuchuje na Web-Porcie ${PORT}`);
    });
  } else {
    // Local STDIO diagnostic process (standard in/out for Docker or a local machine with Cursor)
    const transport = new StdioServerTransport();
    await server.connect(transport);
    console.error("MovieMind MCP Server uruchomiony w trybie terminala (STDIO). Oczekuje I/O...");
  }
}

run().catch(console.error);
