# Kurs tworzenia serwera MCP

Ten dokument jest praktycznym wprowadzeniem do budowy serwera MCP.
Bazuje na tym, jak działa `mcp-server` w tym repo, ale prowadzi od
prostego przykładu do wersji, ktora potrafi udostepniac narzedzia,
zasoby i prompty.

## Co to jest MCP

MCP, czyli Model Context Protocol, to standard komunikacji miedzy
modelem AI a zewnetrznymi systemami. W praktyce oznacza to, ze model
nie musi zgadywac, co jest w bazie danych, logach albo API.
Zamiast tego moze zapytac o to Twoj serwer MCP.

Najprostszy obraz:

- host to aplikacja z modelem, na przyklad Cursor albo Claude Desktop
- client to warstwa wbudowana w hosta, ktora rozmawia z serwerem
- server to Twoj program, ktory wystawia mozliwosci dla modelu

## Jak myslec o serwerze MCP

Serwer MCP nie jest "chatbotem". To adapter miedzy AI a Twoim systemem.

Najczesciej wystawia trzy rzeczy:

- `tools` - akcje, ktore model moze wywolac, na przyklad wyszukaj film albo sprawdz status joba
- `resources` - dane tylko do odczytu, na przyklad schemat bazy, logi albo slownik tlumaczen
- `prompts` - gotowe szablony, ktore podpowiadaja modelowi, jak ma pracowac z Twoim kontekstem

W tym repo dokladnie to widac w `mcp-server/src/index.ts`:

- serwer rejestruje `resources`, `tools` i `prompts`
- korzysta z PostgreSQL przez `pg`
- obsluguje dwa transporty: `stdio` i `SSE`

## Jak dziala obecny `mcp-server`

Obecna implementacja robi kilka prostych, ale waznych rzeczy:

1. Tworzy serwer przez `@modelcontextprotocol/sdk`.
2. Deklaruje capabilities dla `resources`, `tools` i `prompts`.
3. Wystawia narzedzia takie jak:
   - `search_database_movies`
   - `check_job_status`
   - `generate_ai_description`
   - `dispatch_job_retry`
4. Wystawia zasoby, na przyklad:
   - `moviemind://database/schema-summary`
   - `moviemind://logs/laravel-recent`
5. Wystawia prompt `recommend_movies_by_actor`.
6. Potrafi dzialac lokalnie przez `stdio` albo sieciowo przez `SSE`.

To jest dobry szkielet startowy, bo pokazuje pelny przeplyw:

- model widzi liste mozliwosci
- wybiera odpowiednie narzedzie albo zasob
- serwer wykonuje logike
- wynik wraca do modelu w ustandaryzowanym formacie

## Kiedy wybrac `stdio`, a kiedy `SSE`

`stdio` wybierz wtedy, gdy serwer ma byc lokalny i uruchamiany jako
proces potomny przez IDE albo aplikacje desktopowa. To najprostsza
i najbezpieczniejsza opcja do developmentu.

`SSE` wybierz wtedy, gdy serwer ma byc dostepny przez HTTP, na
przyklad dla zewnetrznego klienta, chatu webowego albo innej uslugi
dzialajacej w sieci.

W tym projekcie:

- `stdio` pasuje do pracy lokalnej z Cursor lub Claude Desktop
- `SSE` pasuje do zdalnego, hostowanego serwera MCP

## Kurs: budujemy minimalny serwer krok po kroku

### Krok 1. Inicjalizacja projektu

```bash
mkdir my-mcp-server
cd my-mcp-server
npm init -y
npm install @modelcontextprotocol/sdk
npm install -D typescript ts-node @types/node
npx tsc --init
```

Jesli chcesz pracowac jak w tym repo, od razu warto dolozyc:

```bash
npm install express cors dotenv pg
npm install -D @types/express @types/cors @types/pg
```

### Krok 2. Minimalny serwer z jednym narzedziem

Utworz plik `src/index.ts`:

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

To jest najmniejszy sensowny serwer MCP:

- `ListTools` mowi modelowi, co jest dostepne
- `CallTool` wykonuje logike
- `StdioServerTransport` umozliwia polaczenie lokalne

### Krok 3. Dodanie zasobow

Zasob to cos, co model moze odczytac bez wykonywania akcji zapisu.
Dobrze nadaje sie do logow, konfiguracji, dokumentacji i podsumowan
schematow.

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

W MovieMind podobna idea jest uzyta dla `moviemind://database/schema-summary` i `moviemind://logs/laravel-recent`.

### Krok 4. Dodanie promptow

Prompt w MCP nie zastepuje system prompta modelu. To raczej wygodny
punkt startowy. Pozwala przygotowac gotowe wiadomosci i czasem zaszyc
wskazowke, jak model ma uzyc Twoich zasobow lub narzedzi.

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

### Krok 5. Podlaczenie do bazy danych

Tu zaczyna sie prawdziwy sens MCP. Narzedzie nie musi byc sztuczne.
Moze wykonywac realne zapytania do bazy albo wywolywac Twoje API.

Przyklad podobny do `search_database_movies`:

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

Najwazniejsza zasada: model ma dostac proste, bezpieczne API, a nie surowy dostep do wszystkiego.

## Jak rozszerzyc serwer do wersji podobnej do MovieMind

Minimalny serwer szybko zacznie rosnac. Dobra kolejnosc rozwoju zwykle wyglada tak:

1. Zacznij od jednego narzedzia w `stdio`.
2. Dodaj jeden zasob tylko do odczytu.
3. Dodaj walidacje argumentow i sensowne bledy.
4. Wyciagnij logike biznesowa do osobnych funkcji lub serwisow.
5. Dopiero potem dodaj warstwe HTTP i `SSE`.

W praktyce serwer podobny do `MovieMind` powinien miec podzial na:

- definicje narzedzi
- implementacje handlerow
- klienty do bazy lub API
- walidacje danych wejsciowych
- autoryzacje i rate limiting dla trybu sieciowego

## Dodanie transportu `SSE`

Jesli chcesz, aby serwer byl dostepny przez HTTP, potrzebujesz
webowego transportu. W aktualnym `mcp-server/src/index.ts` robi to
`express` plus `SSEServerTransport`.

Schemat jest prosty:

1. startujesz aplikacje HTTP
2. tworzysz endpoint do zestawienia polaczenia SSE
3. tworzysz endpoint do odbierania wiadomosci
4. przed wpuszczeniem klienta sprawdzasz autoryzacje

Minimalny szkic:

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

To wystarcza do prototypu. Do produkcji trzeba dolozyc bezpieczenstwo.

## Bezpieczenstwo

Serwer MCP bardzo latwo moze stac sie zbyt potezny. Dlatego:

- nie wystawiaj destrukcyjnych narzedzi bez autoryzacji
- waliduj wszystkie argumenty
- dawaj modelowi minimalne uprawnienia
- unikaj narzedzi typu "wykonaj dowolna komende shell"
- loguj wywolania narzedzi
- ogranicz dostep w trybie `SSE` tokenem lub innym mechanizmem auth

W obecnym `mcp-server` jest prosty bearer token dla trybu `SSE`.
To dobry start, ale w wersji produkcyjnej warto dolozyc:

- rotacje tokenow
- rate limiting
- rozdzielenie uprawnien na role lub grupy narzedzi
- audyt wywolan

## Jak podlaczyc serwer do Cursora lub Claude Desktop

Lokalnie najlatwiej zaczac od `stdio`. Host uruchamia Twoj proces
i rozmawia z nim przez standardowe wejscie i wyjscie.

Przykladowa konfiguracja:

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

Jesli budujesz serwer w TypeScript, zwykle masz dwie opcje:

- uruchamiasz skompilowany plik `build/index.js`
- albo uzywasz `ts-node` w trybie developerskim

## Najczestsze bledy na starcie

- za duzo logiki w jednym pliku
- brak rozroznienia miedzy `tool` i `resource`
- brak walidacji argumentow
- wystawienie zbyt szerokich uprawnien
- probowanie zrobienia wszystkiego od razu: baza, prompty, SSE, auth i deployment

Najlepsza droga jest nudna, ale skuteczna: najpierw jedno narzedzie,
potem jeden zasob, potem porzadne bledy, a dopiero na koncu transport
sieciowy.

## Proponowana struktura katalogow

Przy wiekszym serwerze dobrze dziala taki uklad:

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

To nie jest wymagane. Po prostu szybciej utrzymac taki kod, gdy
serwer przestaje byc malym eksperymentem.

## Plan nauki na 60 minut

Jesli chcesz zrozumiec MCP bez toniecia w detalach, zrob to tak:

1. Uruchom minimalny serwer z jednym `tool`.
2. Podepnij go lokalnie przez `stdio`.
3. Dodaj jeden `resource`.
4. Dodaj jeden `prompt`.
5. Zamien mocka na prawdziwe zapytanie do bazy lub API.
6. Na koniec dopiero dodaj `SSE`.

Po takim cwiczeniu wszystko zaczyna byc czytelne, bo widzisz ten sam
wzorzec w kolko: deklaracja mozliwosci, handler, transport, wynik.

## Podsumowanie

Serwer MCP to cienka warstwa integracyjna. Jego zadaniem nie jest
"bycie inteligentnym", tylko bezpieczne i przewidywalne udostepnienie
mozliwosci Twojego systemu modelowi.

W kontekscie MovieMind oznacza to:

- model moze czytac zdefiniowane zasoby
- model moze wywolywac ograniczony zestaw narzedzi
- model moze startowac z gotowych promptow
- to samo rozwiazanie moze dzialac lokalnie przez `stdio` i zdalnie przez `SSE`

Jesli chcesz rozwijac ten serwer dalej, najlepszy nastepny krok to
rozdzielenie `mcp-server/src/index.ts` na mniejsze moduly i dodanie
twardej walidacji argumentow dla kazdego narzedzia.
