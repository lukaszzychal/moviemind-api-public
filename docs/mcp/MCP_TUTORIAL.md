# 🚀 Step by Step to build own Server MCP

Model Context Protocol (MCP) to otwarty standard wprowadzony m.in. przez firmę Anthropic. Pozwala on modelom językowym AI (np. Claude) na standaryzowaną, bezpieczną i bardzo łatwą interakcję z Twoimi lokalnymi/zdalnymi danymi, systemami oraz narzędziami inżynieryjnymi.

## 🏗️ Architektura MCP

Architektura całego środowiska składa się z 3 elementów:
- **MCP Host:** Aplikacja, w której funkcjonuje model AI (np. aplikacja Claude Desktop, dedykowane narzędzia IDE takie jak Cursor/Windsurf).
- **MCP Client:** Klient wbudowany wewnątrz Hosta. Nawiązuje i utrzymuje stabilne połączenie 1:1 w formacie klient-serwer.
- **MCP Server:** Twój własny program (serwer MCP), który udostępnia konkretne dane (Zasoby), narzędzia (Tools) oraz polecenia. Działa lokalnie lub w chmurze i rozmawia z klientem.

---

## 🧩 Najważniejsze Komponenty (Z czym to się je)

Aby pojąć logikę protokołu, musisz zrozumieć trzy byty, które obsługuje MCP:

1. **Zasoby (Resources):** Dane w trybie *tylko do odczytu*, które Twój serwer udostępnia do analizy przez AI. Zachowują się jak wirtualne systemy plików. Mają swoje URI (np. `file:///logs/error.log`, `postgres://schema`). Przykłady: schematy baz danych, pliki logów, dokumentacja API zespołu.
2. **Narzędzia (Tools):** Deklaratywne funkcje (podobnie do "function calling"), które model AI decyduje się samodzielnie wywołać, podając im argumenty. Przykłady: Zrób kwerendę (SELECT), wykonaj restart Nginx, zgłoś błąd w Jira.
3. **Prompty (Prompts):** Ulubione, pre-konfigurowane szablony zapytań ułatwiające pracę użytkownika, mogące automatycznie zasysać dane i nakierowywać AI by je zinterpretowało. (Należy do nich m.in. kontekst do asystenta "Przeanalizuj dzisiejsze logi błędów i poszukaj bugów w pamięci").

---

## 🛠️ Tutorial: Tworzymy własny Serwer (TypeScript / Node.js)

Jako punkt wyjścia wykorzystamy środowisko TypeScript, ponieważ posiada fenomenalnie rozbudowane, oficjalne pakiety od twórców protokołu (`@modelcontextprotocol/sdk`).

### Krok 1: Inicjalizacja projektu
Rozpocznij tworząc nowy folder dla serwera deweloperskiego. W CLI wywołaj:

```bash
mkdir my-mcp-server && cd my-mcp-server
npm init -y
npm install @modelcontextprotocol/sdk
npm install -D typescript @types/node ts-node
npx tsc --init
```

### Krok 2: Tworzenie gniazda serwera (Plik wejściowy)
Utwórz plik `index.ts`. Zdefiniujemy tam klasę i nadamy naszemu serwerowi nazwę.

```typescript
import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { CallToolRequestSchema, ListToolsRequestSchema } from "@modelcontextprotocol/sdk/types.js";

// 1. Inicjalizacja Serwera - Rejestrujemy funkcje
const server = new Server({
    name: "moj-pierwszy-mcp-server",
    version: "1.0.0"
}, {
    capabilities: {
        tools: {} // Będziemy udsotępniać AI nasze własne Narzędzia
    }
});
```

### Krok 3: Udostępnienie listy Narzędzi (Tools)
Aby AI "wiedziało", co w ogóle może zrobić, podepnij w `index.ts` obsługę żądania schematu listy narzędzi (`ListTools`):

```typescript
server.setRequestHandler(ListToolsRequestSchema, async () => {
    return {
        tools: [
            {
                name: "powitaj_mnie",
                description: "Zwraca powitanie dla podanego imienia.",
                inputSchema: {
                    type: "object",
                    properties: {
                        name: { type: "string", description: "Imię do przywitania" }
                    },
                    required: ["name"]
                }
            }
        ]
    };
});
```

### Krok 4: Wykonanie logiki narzędzia (Call Tool)
Musisz przechwycić żądanie wykonania metody od modelu i wstrzyknąć tam prawdziwy kod wykonawczy serwera w NodeJS.

```typescript
server.setRequestHandler(CallToolRequestSchema, async (request) => {
    if (request.params.name === "powitaj_mnie") {
        const userName = request.params.arguments?.name as string;
        
        // Zwracasz określoną treść, która zostaje przesłana do Hosta (AI)
        return {
            content: [
                {
                    type: "text",
                    text: `Witaj, ${userName}! Pozdrowienia prosto z Serwera MCP.`
                }
            ]
        };
    }
    
    throw new Error("Wywołano niprawidłowe narzędzie.");
});
```

### Krok 5: Aktywacja Standardowego Wejścia-Wyjścia (STDIO)
Komunikacja z aplikacjami takimi jak Claude dla Win/Mac wykorzystuje głównie wyjście strumieniowe środowiska STDIO (standard in/out). 

```typescript
async function main() {
    // Łączymy serwer przez proces transportowy Stdio
    const transport = new StdioServerTransport();
    await server.connect(transport);
    console.error("Serwer MCP pomyślnie działa i nasłuchuje!");
}

main().catch(console.error);
```

### Krok 6: Podłączenie pod swojego Hosta (Claude Desktop)
To jest proces w którym AI otrzymuje od Ciebie "wiedzę". Używając Claude Desktop, otwierasz jego plik konfiguracyjny (np. na mac: `~/Library/Application Support/Claude/claude_desktop_config.json`)

```json
{
  "mcpServers": {
    "moj-serwer-nodejs": {
      "command": "npx",
      "args": [
        "ts-node",
        "/absolutna/sciezka/na/dysku/do/my-mcp-server/index.ts"
      ]
    }
  }
}
```

Uruchom ponownie Claude'a! Zobaczysz obok czatu ikonkę wtyczki z Narzędziami. Od tej pory możesz do niego napisać *"Przetestuj swoje narzędzia i powitaj mnie imieniem Łukasz"*, a bot bezbłędnie przekaże odpowiednią funkcję do Twojego stworzonego skryptu JS by uzyskać oczekiwaną odpowiedź. 

Możesz dobudować tam tysiące innych funkcji - podpięcia pod zewnętrzną infrastrukturę, gniazdowo do baz danych i nie tylko!

---

## 📚 Krok 7: Zastosowanie Zasobów (Resources)
Narzędzia (Tools) to "ręce" sztucznej inteligencji, ale do pełnego sukcesu potrzebujemy dać jej "oczy". Oczy zyskuje używając zasobów, które pozwalają AI odczytywać zdefiniowany kontekst.

- **Przykład użycia:** Chcesz by bot potrafił w locie odczytać aktualne logi `laravel.log` na zapytanie bez kopiowania sterty linii wklejając je ręcznie do czatu.

Najpierw na serwerze oświadczamy światu (modelom AI), że posiadamy takie logi na pokładzie poprzez obsługę `ListResources`:

```typescript
import { ListResourcesRequestSchema, ReadResourceRequestSchema } from "@modelcontextprotocol/sdk/types.js";

// Ujawniamy dostępne na tym serwerze wirtualne pliki/URI
server.setRequestHandler(ListResourcesRequestSchema, async () => {
    return {
        resources: [
            {
                uri: "moviemind://logs/error.log",
                name: "Logi Aplikacji MovieMind",
                mimeType: "text/plain",
                description: "Zawiera ostatnie wpisy z pliku errorów."
            }
        ]
    };
});
```

Następnie definiujemy fragment logiki odpowiedzialny za rzeczywiste odczytanie zasobu, gdy sprytny model postanowi go pobrać z podanego URI:

```typescript
// Reagujemy, jeżeli AI zgłosi ochotę dostępu do naszej bazy danych (poprzez wewnęztrzne URI)
server.setRequestHandler(ReadResourceRequestSchema, async (request) => {
    if (request.params.uri === "moviemind://logs/error.log") {
        
        // Zależnie od środowiska: fs.readFileSync('./storage/logs/...') w Node.JS 
        // W PHP: File::get(storage_path('logs/laravel.log'))
        const mockLogLines = "[2026-03-24] production.ERROR: Connect Exception: Connection refused...";
        
        return {
            contents: [
                {
                    uri: "moviemind://logs/error.log",
                    mimeType: "text/plain",
                    text: mockLogLines
                }
            ]
        };
    }
    
    throw new Error("Nie znaleziono zasobu");
});
```
**EFEKT:** Twój klient (Claude) potrafi wyświetlić wewnętrzne zasoby ze struktury `moviemind://`. Od teraz mówiąc "Zbadaj co się dzieje z nowymi błędami", Claude wysyła request na backend, by pobrać plik!

---

## 🎯 Krok 8: Wstrzykiwanie Promptów (Prompts)
Prompty pełnią funkcję Twoich spersonalizowanych "skrótów" deweloperskich w GUI asystenta. Integrują one model myślenia narzędzi AI i nakazują im zintegrować uprzednio podpięte zasoby z pożądanymi poleceniami.

Proces deklaracji zaczynamy po raz kolejny od opublikowania listy `ListPrompts`:

```typescript
import { ListPromptsRequestSchema, GetPromptRequestSchema } from "@modelcontextprotocol/sdk/types.js";

server.setRequestHandler(ListPromptsRequestSchema, async () => {
    return {
        prompts: [
            {
                name: "analizuj_kody_bledow",
                description: "Odczytuje logi z serwera i zmusza AI do wykonania na nich audytu zabezpieczeń i wyjątków."
            }
        ]
    };
});
```

Teraz, w momencie wywołania tej flagi (np. w menu interfejsu z asystentem pod guzikiem), zwracamy instrukcje kompilowane dla asystenta:

```typescript
server.setRequestHandler(GetPromptRequestSchema, async (request) => {
    if (request.params.name === "analizuj_kody_bledow") {
        return {
            description: "Moduł do audytu logów",
            messages: [
                {
                    // Definitywne polecenie zachowania
                    role: "user",
                    content: {
                        type: "text",
                        text: "Jesteś ekspertem DevOps. Przeanalizuj dołączony plik z logami i wypisz mi listę punktów krytycznych awarii."
                    }
                },
                {
                    // Magia MCP - powiązujemy zasób. Model sam wyciagnie i doklei zasób do zapytań!
                    role: "user",
                    content: {
                        type: "resource",
                        resource: {
                            uri: "moviemind://logs/error.log",
                            text: "Podłączone najświeższe logi systemowe." // Opcjonalny deskryptor podpiętych danych
                        }
                    }
                }
            ]
        };
    }
    
    throw new Error("Taki prompt powitalny nie istnieje w serwerze.");
});
```

**EFEKT:** Kliknięcie jednego polecenia w interfejsie zażąda od LLM rozbudowanego i przygotowanego myślenia kontekstowego. AI połączy URI i wykona skrupulatną recenzję (np. szukając SQL injections z boku). Nie musisz powtarzać mu co ma robić ani przeklejać treści – robi to sam z użyciem Twojego Serwera MCP. 🚀
