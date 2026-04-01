# MCP server course

This document is a practical introduction to building an MCP server.
It uses the `mcp-server` in this repository as a reference point, but
starts from a minimal example and works up to a version that exposes
tools, resources, and prompts.

## What MCP is

MCP, or Model Context Protocol, is a standard for communication
between an AI model and external systems. In practice, it means the
model does not have to guess what is in your database, logs, or API.
It can ask your MCP server instead.

The simplest mental model looks like this:

- the host is the application that runs the model, such as Cursor or Claude Desktop
- the client is the layer inside the host that talks to the server
- the server is your program that exposes capabilities to the model

## How to think about an MCP server

An MCP server is not a chatbot. It is an adapter between AI and your system.

It usually exposes three things:

- `tools` - actions the model can call, such as searching for a movie or checking a job status
- `resources` - read-only data, such as a schema summary, logs, or translation files
- `prompts` - prepared starting points that guide the model in a specific workflow

That is exactly what happens in `mcp-server/src/index.ts` in this repository:

- the server registers `resources`, `tools`, and `prompts`
- it uses PostgreSQL through `pg`
- it supports two transports: `stdio` and `SSE`

## How the current `mcp-server` works

The current implementation does a few simple but important things:

1. It creates a server with `@modelcontextprotocol/sdk`.
2. It declares capabilities for `resources`, `tools`, and `prompts`.
3. It exposes tools such as:
   - `search_database_movies`
   - `check_job_status`
   - `generate_ai_description`
   - `dispatch_job_retry`
4. It exposes resources such as:
   - `moviemind://database/schema-summary`
   - `moviemind://logs/laravel-recent`
5. It exposes the prompt `recommend_movies_by_actor`.
6. It can run locally through `stdio` or over the network through `SSE`.

This is a good starter shape because it shows the full loop:

- the model sees what is available
- it picks a tool or resource
- the server runs the underlying logic
- the result comes back in a standard format

## When to use `stdio` and when to use `SSE`

Use `stdio` when the server is local and launched as a child process
by an IDE or desktop app. It is the simplest and safest option for
development.

Use `SSE` when the server needs to be reachable over HTTP, for example by a web chat, another service, or a hosted environment.

In this project:

- `stdio` fits local work with Cursor or Claude Desktop
- `SSE` fits a hosted or remote MCP server

## Course: build a minimal server step by step

### Step 1. Initialize the project

```bash
mkdir my-mcp-server
cd my-mcp-server
npm init -y
npm install @modelcontextprotocol/sdk
npm install -D typescript ts-node @types/node
npx tsc --init
```

If you want a setup closer to this repository, add these too:

```bash
npm install express cors dotenv pg
npm install -D @types/express @types/cors @types/pg
```

### Step 2. Create a minimal server with one tool

Create `src/index.ts`:

```ts
import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";

const server = new Server(
  {
    name: "my-first-mcp-server",
    version: "1.0.0",
  },
  {
    capabilities: {
      tools: {},
    },
  }
);

server.setRequestHandler(ListToolsRequestSchema, async () => {
  return {
    tools: [
      {
        name: "say_hello",
        description: "Returns a greeting for the given name.",
        inputSchema: {
          type: "object",
          properties: {
            name: { type: "string" },
          },
          required: ["name"],
        },
      },
    ],
  };
});

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  if (request.params.name !== "say_hello") {
    throw new Error(`Unknown tool: ${request.params.name}`);
  }

  const name = String(request.params.arguments?.name ?? "friend");

  return {
    content: [
      {
        type: "text",
        text: `Hello, ${name}!`,
      },
    ],
  };
});

async function main(): Promise<void> {
  const transport = new StdioServerTransport();
  await server.connect(transport);
}

main().catch(console.error);
```

This is the smallest useful MCP server:

- `ListTools` tells the model what is available
- `CallTool` runs the logic
- `StdioServerTransport` enables a local connection

### Step 3. Add resources

A resource is something the model can read without performing a write
action. It works well for logs, config, docs, and schema summaries.

```ts
import {
  ListResourcesRequestSchema,
  ReadResourceRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";

server.setRequestHandler(ListResourcesRequestSchema, async () => {
  return {
    resources: [
      {
        uri: "demo://app/version",
        name: "Application version",
        mimeType: "text/plain",
        description: "Current application version.",
      },
    ],
  };
});

server.setRequestHandler(ReadResourceRequestSchema, async (request) => {
  if (request.params.uri !== "demo://app/version") {
    throw new Error(`Unknown resource: ${request.params.uri}`);
  }

  return {
    contents: [
      {
        uri: "demo://app/version",
        mimeType: "text/plain",
        text: "1.0.0",
      },
    ],
  };
});
```

In MovieMind, the same idea is used for `moviemind://database/schema-summary` and `moviemind://logs/laravel-recent`.

### Step 4. Add prompts

A prompt in MCP does not replace the model's system prompt. It is
more like a prepared entry point. It gives the model a useful
starting instruction and can hint at how to use your tools or
resources.

```ts
import {
  GetPromptRequestSchema,
  ListPromptsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";

server.setRequestHandler(ListPromptsRequestSchema, async () => {
  return {
    prompts: [
      {
        name: "review_logs",
        description: "Ask the model to inspect recent logs.",
      },
    ],
  };
});

server.setRequestHandler(GetPromptRequestSchema, async (request) => {
  if (request.params.name !== "review_logs") {
    throw new Error(`Unknown prompt: ${request.params.name}`);
  }

  return {
    description: "Analyze recent application logs",
    messages: [
      {
        role: "user",
        content: {
          type: "text",
          text: "Read the recent logs and summarize the most likely failure.",
        },
      },
    ],
  };
});
```

### Step 5. Connect to a database

This is where MCP becomes truly useful. Your tool does not have to be
a toy example. It can query a real database or call your API.

A sketch close to `search_database_movies`:

```ts
import { Pool } from "pg";

const pool = new Pool({
  host: process.env.DB_HOST,
  port: Number(process.env.DB_PORT ?? "5432"),
  user: process.env.DB_USERNAME,
  password: process.env.DB_PASSWORD,
  database: process.env.DB_DATABASE,
});

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  if (request.params.name === "search_movies") {
    const query = String(request.params.arguments?.query ?? "");

    const result = await pool.query(
      "SELECT id, title FROM movies WHERE title ILIKE $1 LIMIT 5",
      [`%${query}%`]
    );

    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result.rows, null, 2),
        },
      ],
    };
  }

  throw new Error(`Unknown tool: ${request.params.name}`);
});
```

The main rule is simple: give the model a narrow, safe API instead of raw access to everything.

## How to grow the server into something like MovieMind

A minimal server grows quickly. A good development order usually looks like this:

1. Start with one tool in `stdio`.
2. Add one read-only resource.
3. Add validation and useful error handling.
4. Move business logic into separate functions or services.
5. Only then add HTTP and `SSE`.

In practice, a server like `MovieMind` should be split into:

- tool definitions
- handler implementations
- database or API clients
- input validation
- auth and rate limiting for network mode

## Add `SSE` transport

If you want the server to be reachable over HTTP, you need a web
transport. In the current `mcp-server/src/index.ts`, this is handled
by `express` plus `SSEServerTransport`.

The shape is straightforward:

1. start an HTTP app
2. create an endpoint to establish the SSE connection
3. create an endpoint to receive messages
4. verify auth before letting the client in

Minimal sketch:

```ts
import express from "express";
import { SSEServerTransport } from "@modelcontextprotocol/sdk/server/sse.js";

const app = express();
let transport: SSEServerTransport | null = null;

app.get("/sse", async (_req, res) => {
  transport = new SSEServerTransport("/message", res);
  await server.connect(transport);
});

app.post("/message", async (req, res) => {
  if (!transport) {
    res.status(400).send("No active SSE connection.");
    return;
  }

  await transport.handlePostMessage(req, res);
});

app.listen(8080);
```

That is enough for a prototype. Production needs more protection.

## Security

An MCP server can become too powerful very quickly. Because of that:

- do not expose destructive tools without auth
- validate all arguments
- give the model the minimum permissions it needs
- avoid tools like "run any shell command"
- log tool calls
- protect `SSE` mode with a token or another auth layer

The current `mcp-server` uses a simple bearer token in `SSE` mode.
That is a decent starting point, but a production version should also
consider:

- token rotation
- rate limiting
- role-based access or tool groups
- audit trails

## Connect the server to Cursor or Claude Desktop

Locally, the easiest place to start is `stdio`. The host starts your
process and communicates with it through standard input and output.

Example configuration:

```json
{
  "mcpServers": {
    "my-server": {
      "command": "node",
      "args": ["/absolute/path/to/build/index.js"]
    }
  }
}
```

If you build the server in TypeScript, you usually have two options:

- run the compiled `build/index.js`
- or use `ts-node` in development

## Common mistakes at the beginning

- putting too much logic into one file
- mixing up `tool` and `resource`
- skipping argument validation
- exposing permissions that are too broad
- trying to do everything at once: database, prompts, SSE, auth, and deployment

The best path is a boring one: one tool first, then one resource,
then proper error handling, and only after that the network transport.

## Suggested directory structure

For a larger server, this structure usually works well:

```text
src/
  index.ts
  server/
    createServer.ts
  tools/
    listTools.ts
    handlers/
      searchMovies.ts
      checkJobStatus.ts
  resources/
    listResources.ts
    handlers/
      readSchemaSummary.ts
  prompts/
    listPrompts.ts
    handlers/
      getRecommendMoviesPrompt.ts
  infra/
    db.ts
    env.ts
```

This is not mandatory. It simply becomes much easier to maintain once
the server stops being a small experiment.

## A 60-minute learning plan

If you want to understand MCP without getting lost in details, do this:

1. Run a minimal server with one `tool`.
2. Connect it locally through `stdio`.
3. Add one `resource`.
4. Add one `prompt`.
5. Replace the mock with a real database query or API call.
6. Add `SSE` last.

After that exercise, the whole pattern becomes clear because you keep
seeing the same loop: capability declaration, handler, transport,
result.

## Summary

An MCP server is a thin integration layer. Its job is not to be
intelligent. Its job is to expose your system's capabilities to the
model in a safe and predictable way.

In the context of MovieMind, that means:

- the model can read defined resources
- the model can call a limited set of tools
- the model can start from prepared prompts
- the same solution can work locally over `stdio` and remotely over `SSE`

If you want to keep improving this server, the best next step is to
split `mcp-server/src/index.ts` into smaller modules and add strict
argument validation for each tool.
