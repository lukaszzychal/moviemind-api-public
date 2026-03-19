# 📘 MovieMind MCP Server – Instrukcja Obsługi (User Manual)

Serwer MCP został zbudowany w środowisku Node.js z racji natywnego wsparcia wielowątkowości w bibliotece `@modelcontextprotocol/sdk`. Aplikację można odpalić zarówno do odpytywania z panelu administratora (Diagnostics), jak i obsługi użytkowników z frontendu Vue.js na produkcji (Chatbots).

---

## 💻 1. Uruchomienie deweloperskie – "DevOps MCP" (Tryb wiersza poleceń - Stdio)
Gdy jako deweloper pracujesz nad kodem, Claude na Twoim komputerze łączy się z uruchomionym serwerem w ukryty i "bezdźwięczny" sposób wysyłając strumienie tekstu bez otwierania portów sieciowych.

**Krok 1: Instalacja u Ciebie:**
W oknie terminala zainstaluj zależności w folderze z nowym serwerem:
```bash
cd mcp-server
npm install
npm run build
```

**Krok 2: Instalacja serwera w Dockerze:**
Najbardziej optymalne jest odpalanie serwera obok bazy danych (w wirtualnym dysku Laravel'a). 
Ale aby móc lokalnie podpinać się pod pracujący silnik wygenerowany komendą `npm run build`, podłącz go do configu Twojego narzędzia diagnostycznego na Twoim systemie (Np. desktopowy Claude lub Cursor).

Plik konfiguracyjny (np. w Cursorze *Cursor Settings > Features > MCP* albo plik macowy dla Claude'a - `~/Library/Application Support/Claude/claude_desktop_config.json`):
```json
{
  "mcpServers": {
    "moviemind_devops": {
      "command": "node",
      "args": [
        "/absolute/path/to/moviemind-api-public/mcp-server/build/index.js"
      ],
      "env": {
         "TRANSPORT_TYPE": "stdio",
         "DB_HOST": "localhost",
         "DB_PORT": "5432"
      }
    }
  }
}
```

Od tego momentu wystarczy zapytać Claude'a: *"Zresetuj wszystkie faile z moich AiJobów na backendzie"*, a uruchomi z serwera funkcję `dispatch_job_retry` lub *"Wylistuj mi wczorajszą usterkę z logsów!"* poprzez zaciśnięcie narzędzia logu `moviemind://logs/laravel-recent`.

---

## 🌐 2. Uruchomienie chmurowe na Produkcji – "End-User MCP" (Tryb Sieciowy HTTP - SSE)

Kluczową zaletą nowego serwera jest udostępnienie go publicznie, zapięcie za Token, aby mogła odpytywać go np. chmura bota Czatowego ze strony Front-end. Oparty on jest na jednostronnych webhookach strumieniowych `SSE` (Server-Sent Events).

**Jak to wywołać na hostingu np. Railway:**
Serwer startuje demonem z wstrzykniętymi odpowiednimi zmiennymi ENV:
```bash
export TRANSPORT_TYPE=sse
export AUTH_TOKEN=secret_moviemind_prod_key_123
export PORT=8080
export DB_HOST=postgres.railway.internal
node build/index.js
```

**Konfiguracja na Froncie albo zintegrowanym systemie AI:**
Zbudowana aplikacja kliencka, aby autoryzować wysłanie zadania z okienka Czatbota z bazy i pobrania odpowiedzi, nawiązuje klasyczne połączenie SSE pod dedykowany `/sse` trzymając w nagłówku token Bearer zabezpieczający naszą bazę. Skrypt automatycznie wykryje polecenie i wykona zlecony Action, wysyłając zapytanie o dodanie Joba do tabelki Laravela!

*(Sposób integracji kodu z widokiem konwersacji Czatbota np. OpenAI w pliku `Search.vue` jest przedmiotem instalacji klienta `Client` pakietu mcp/sdk po stronie VUE).*

---

## 🐳 3. Uruchomienie lokalne w Dockerze – tryb SSE (Testowanie Chatbota przed wdrożeniem na Railway)

Masz tu trzecią, hybrydową opcję: uruchamiasz serwer MCP **w Dockerze lokalnie**, ale w trybie **SSE** (nie Stdio). Dzięki temu zarówno Twój Chatbot Vue.js w przeglądarce jak i Claude Desktop mogą łączyć się z nim przez HTTP - bez konieczności wpychania czegokolwiek w produkcję Railway. Idealne do testów end-to-end przed merżem.

**Krok 1: Dodaj usługę do `docker-compose.yml`:**

```yaml
services:
  # ... (postgres, redis, backend, frontend - jak dotychczas)

  mcp_server:
    build: ./mcp-server
    container_name: moviemind_mcp_server
    restart: unless-stopped
    ports:
      - "8080:8080"       # Wystawia port lokalnie na Twoim laptopie
    environment:
      TRANSPORT_TYPE: "sse"
      AUTH_TOKEN: "local_dev_token_mcp"
      PORT: 8080
      DB_HOST: postgres   # Nazwa usługi docker-compose
      DB_PORT: 5432
      DB_USERNAME: sail
      DB_PASSWORD: password
      DB_DATABASE: moviemind
    depends_on:
      - postgres
    networks:
      - moviemind

networks:
  moviemind:
    driver: bridge
```

**Krok 2: Utwórz `mcp-server/Dockerfile`:**

```dockerfile
FROM node:20-alpine
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build
CMD ["node", "build/index.js"]
```

**Krok 3: Uruchom poleceniem:**

```bash
docker compose up mcp_server
```

Serwer będzie nasłuchiwał na `http://localhost:8080/sse`.

**Krok 4: Podłącz Claude Desktop (opcja lokalna przez HTTP SSE):**

W pliku `~/Library/Application Support/Claude/claude_desktop_config.json` zmień `command` na podłączenie przez SSE URL:

```json
{
  "mcpServers": {
    "moviemind_local_sse": {
      "type": "sse",
      "url": "http://localhost:8080/sse",
      "headers": {
        "Authorization": "Bearer local_dev_token_mcp"
      }
    }
  }
}
```

**Porównanie wszystkich trybów:**

| | Stdio (lokalny) | SSE – Docker lokalny | SSE – Railway (prod) |
|---|---|---|---|
| **Uruchomienie** | `node build/index.js` | `docker compose up` | Automatycznie z deploy |
| **Połączenie** | Bezpośrednio przez terminal | `http://localhost:8080/sse` | `https://mcp.moviemind.app/sse` |
| **Przeznaczenie** | DevOps (Claude Desktop) | Testowanie Chatbota Vue | End-User Produkcja |
| **Bezpieczeństwo** | Bardzo wysokie (brak portów) | Tylko lokalnie (nie w internecie) | Token Bearer + HTTPS |

