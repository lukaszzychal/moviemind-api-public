# Kurs tworzenia serwera MCP

Ten dokument jest praktycznym wprowadzeniem do budowy serwera MCP.
Prowadzi od prostego przykładu do wersji, ktora potrafi udostepniac
narzedzia, zasoby i prompty.

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

Typowy serwer MCP robi kilka prostych, ale waznych rzeczy:

1. Tworzy instancje serwera przez `@modelcontextprotocol/sdk`.
2. Deklaruje capabilities dla `resources`, `tools` i `prompts`.
3. Udostepnia narzedzia, ktore model moze wywolywac.
4. Udostepnia zasoby, ktore model moze odczytywac.
5. Udostepnia prompty jako gotowe punkty startowe.
6. Dziala lokalnie przez `stdio` albo sieciowo przez `SSE`.

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

## Sposoby uruchamiania: lokalnie i w kontenerze

W praktyce masz trzy typowe warianty:

### 1. Lokalnie bez kontenera

Najprostsza opcja na start. Dobra do:

- szybkiego prototypowania
- pracy z `stdio`
- lokalnego podpiecia pod Cursor albo Claude Desktop

Typowy przeplyw:

```bash
npm install
npm run build
npm run start
```

albo w trybie developerskim:

```bash
npm run dev
```

### 2. Lokalnie w kontenerze

To dobry wariant, gdy:

- chcesz miec powtarzalne srodowisko
- nie chcesz instalowac lokalnie Node.js
- planujesz potem wdrozenie do Railway, Render albo Fly.io

W tym modelu budujesz obraz Dockera i uruchamiasz serwer jako osobny
kontener.

### 3. Zdalnie w kontenerze

To naturalna droga dla hostingu. W praktyce:

- budujesz obraz lokalnie albo na platformie CI
- platforma uruchamia kontener
- serwer wystawia `SSE` przez HTTP

Ten wariant jest najczestszy dla publicznych lub pol-publicznych MCP
serverow.

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

## Jak rozszerzyc serwer do wersji produkcyjnej

Minimalny serwer szybko zacznie rosnac. Dobra kolejnosc rozwoju zwykle wyglada tak:

1. Zacznij od jednego narzedzia w `stdio`.
2. Dodaj jeden zasob tylko do odczytu.
3. Dodaj walidacje argumentow i sensowne bledy.
4. Wyciagnij logike biznesowa do osobnych funkcji lub serwisow.
5. Dopiero potem dodaj warstwe HTTP i `SSE`.

W praktyce wiekszy serwer MCP powinien miec podzial na:

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

## Uruchomienie przez Docker

Jesli chcesz odpalic serwer MCP w kontenerze, najprostsza droga to
osobny obraz Dockera, ktory:

- instaluje zaleznosci
- buduje TypeScript
- uruchamia gotowy plik z katalogu `build/`

### Przykladowy `Dockerfile`

Ponizej jest prosty, praktyczny przyklad dla serwera MCP w Node.js
z TypeScript:

```dockerfile
FROM node:20-alpine AS build

WORKDIR /app

COPY package.json package-lock.json tsconfig.json ./
RUN npm ci

COPY src ./src
RUN npm run build

FROM node:20-alpine AS runtime

WORKDIR /app
ENV NODE_ENV=production

COPY package.json package-lock.json ./
RUN npm ci --omit=dev

COPY --from=build /app/build ./build

EXPOSE 8080

CMD ["npm", "run", "start"]
```

### Build obrazu

Uruchom z katalogu projektu serwera:

```bash
docker build -t my-mcp-server .
```

### Start kontenera w trybie `SSE`

Przykladowe uruchomienie:

```bash
docker run --rm -p 8080:8080 \
  -e TRANSPORT_TYPE=sse \
  -e PORT=8080 \
  -e AUTH_TOKEN=replace_me \
  -e DB_HOST=host.docker.internal \
  -e DB_PORT=5432 \
  -e DB_USERNAME=app_user \
  -e DB_PASSWORD=secret \
  -e DB_DATABASE=app_db \
  my-mcp-server
```

Po starcie serwer bedzie dostepny pod:

- `http://localhost:8080/sse`

Jesli klient MCP laczy sie przez HTTP, powinien wysylac naglowek:

```text
Authorization: Bearer replace_me
```

### Co robi ten `Dockerfile`

- w pierwszym etapie instaluje pelne zaleznosci i buduje TypeScript do
  `build/`
- w drugim etapie instaluje tylko zaleznosci produkcyjne
- finalnie uruchamia `npm run start`, czyli `node build/index.js`

To jest dobry wariant pod Railway, Render, Fly.io albo wlasny serwer.
Masz jeden przewidywalny artefakt i nie musisz polegac na lokalnej
instalacji Node.js poza samym Dockerem.

## Popularne wzorce uruchomienia

Nie kazdy serwer MCP musi byc uruchamiany tak samo. W praktyce sa trzy
sensowne wzorce.

### Wzorzec 1: jeden serwer, wszystkie mozliwosci

To najprostszy wariant. Jeden proces wystawia wszystkie `tools`,
`resources` i `prompts`.

Ten uklad jest dobry, gdy:

- budujesz proof of concept,
- pracujesz sam,
- chcesz szybko przetestowac pomysl bez dodatkowej architektury.

Przykladowy start:

```bash
npm run start
```

albo w kontenerze:

```bash
docker run --rm -p 8080:8080 \
  -e TRANSPORT_TYPE=sse \
  -e PORT=8080 \
  -e AUTH_TOKEN=replace_me \
  my-mcp-server
```

Minusem tego podejscia jest to, ze szybko mieszaja sie odpowiedzialnosci.

### Wzorzec 2: jeden serwer, rozne role

To dobry kompromis. Nadal masz jeden kodbase, ale serwer uruchamia sie
w roznych profilach i wystawia tylko czesc mozliwosci.

Najczesciej robi sie to przez zmienna srodowiskowa, na przyklad:

```bash
MCP_ROLE=public npm run start
MCP_ROLE=internal npm run start
```

albo w kontenerze:

```bash
docker run --rm -p 8080:8080 \
  -e MCP_ROLE=public \
  -e TRANSPORT_TYPE=sse \
  -e PORT=8080 \
  -e AUTH_TOKEN=replace_me \
  my-mcp-server
```

```bash
docker run --rm -p 8080:8080 \
  -e MCP_ROLE=internal \
  -e TRANSPORT_TYPE=sse \
  -e PORT=8080 \
  -e AUTH_TOKEN=replace_me \
  my-mcp-server
```

To podejscie sprawdza sie, gdy:

- chcesz wspolnego kodu,
- ale potrzebujesz roznych zestawow narzedzi,
- i chcesz ograniczyc ryzyko wystawienia zbyt szerokiego dostepu.

### Wzorzec 3: osobne serwery MCP

To najbardziej czytelny wariant architektonicznie. Budujesz dwa lub
wiecej osobnych serwerow, z ktorych kazdy ma swoj cel.

Przykladowo:

- jeden serwer do funkcji produktowych,
- drugi do diagnostyki,
- trzeci do pracy z dokumentacja albo wewnetrznymi systemami.

Ten wariant jest dobry, gdy:

- zespol jest wiekszy,
- system ma wyrazne granice odpowiedzialnosci,
- chcesz wdrazac, skalowac albo zabezpieczac te serwery niezaleznie.

### Jak wybrac wzorzec

Najprostsza praktyka jest taka:

- zacznij od jednego serwera, jesli dopiero testujesz MCP,
- przejdz do rol, gdy zaczyna sie mieszac dostep i odpowiedzialnosc,
- wydziel osobne serwery, gdy roznice sa juz trwale i organizacyjnie
  wazne.

## Bezpieczenstwo

Serwer MCP bardzo latwo moze stac sie zbyt potezny. Dlatego:

- nie wystawiaj destrukcyjnych narzedzi bez autoryzacji
- waliduj wszystkie argumenty
- dawaj modelowi minimalne uprawnienia
- unikaj narzedzi typu "wykonaj dowolna komende shell"
- loguj wywolania narzedzi
- ogranicz dostep w trybie `SSE` tokenem lub innym mechanizmem auth

W prostym serwerze bearer token dla trybu `SSE` moze byc dobrym
punktem startowym, ale w wersji produkcyjnej warto dolozyc:

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

W praktyce oznacza to:

- model moze czytac zdefiniowane zasoby
- model moze wywolywac ograniczony zestaw narzedzi
- model moze startowac z gotowych promptow
- to samo rozwiazanie moze dzialac lokalnie przez `stdio` i zdalnie
  przez `SSE`

Jesli chcesz rozwijac taki serwer dalej, najlepszy nastepny krok to
rozdzielenie `index.ts` na mniejsze moduly i dodanie twardej walidacji
argumentow dla kazdego narzedzia.
