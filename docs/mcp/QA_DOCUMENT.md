# 🛡️ Dokumentacja Zapewnieniu Jakości (QA & Testing) - Serwer MCP

Dokument skupia test-case'y niezbędne do przedwdrożeniowego audytu serwisu odpowiedzialnego za dostęp AI do zasobów PostgreSQL i Laravel - by zminimalizować ryzyko dziur oraz "halucynacji" bazy.

## Cel i Płaszczyzna Audytu

Serwer NodeJS (`mcp-server`) operuje dwoma potężnymi wektorami wejścia w platformę MovieMind:
1.  **Odczytem struktur:** `moviemind://(...)` - Logów systemowych (DevOps) i wylistowanych fraz słownych/TMDb.
2.  **Akcjami manipulacyjnymi:** Narzędziami podpinającymi nowo zadane asynchroniczne polecenia (`generate_ai_description`) lub wyszukaniami z bazy.

Zważywszy na asynchroniczny i sztucznie inteligentny wektor wywołujący serwer, błędy potrafią eskalować bardzo łatwo, gubiąc wątek bez asercji brzegowych.

---

### Tabela 1: Weryfikacja Bezpieczeństwa (Transport Zdalny HTTP SSE)

Konstrukcja w klasie transportowej ma nałożony obligatoryjny filtr adresowany wektorom ataku.

| ID | Tytuł Scenariusza (Test Case) | Warunki Początkowe (Preconditions) | Kroki Podjęte | Spodziewany Efekt Pomyślny (Assertion) | Priorytet |
|----|---------------------------------|---------------------------------|------------------|---------------------------------------|-----------|
| **SEC-01** | Weryfikacja odrzucenia bez Bearer | Serwer uruchomiony w trybie wirtualnym `env TRANSPORT_TYPE=sse` | Próba wykonania klienta zapytania WebHook "GET /sse" bez żadnego klucza w nagłówku. | Aplikacja zwraca twardy wyciek `HTTP 401 Unauthorized Access`. Brak zestawienia potoku zdarzeń EventSource. | P1(Krytyczny) |
| **SEC-02** | Weryfikacja akceptacji przez QueryString (Fallback) | JWT wygasł na kliencie. | Podłączenie adresu sieciowego `GET /sse?token=DEBUG_TOKEN123`. | Utworzony po weryfikacji most SSE 24/7 pomyślnie emituje powitalny paczek od instancji "moviemind-mcp-server". | P2 |
| **SEC-03** | Injection na `search_database_movies` | Aplikacja odblokowana | Zapytanie przez chat do bota (narzędzia `query` pola) w którym query SQL wynosi np. `' OR 1=1; DROP TABLE movies; --`. | Parametr wpada do paczki PostgreSQL ORM'u przez mechanizm Bindings (`$1`). Znacznik z dropem traktowany jest po prostu jak ciąg do "ILIKE", nie powodując egzekucji polecenia DML ani Exceptionu. | P1(Krytyczny) |

---

### Tabela 2: Integracja Narzędzi Ekosystemu MovieMind (Integratyka backendowa)

Narzędzia muszą sprawnie wykręcić powiadomienia do Fasad i Zadań tła w rejonie `backend Laravel`.

| ID | Tytuł Scenariusza (Test Case) | Warunki Początkowe (Preconditions) | Kroki Podjęte | Spodziewany Efekt Pomyślny (Assertion) | Priorytet |
|----|---------------------------------|---------------------------------|------------------|---------------------------------------|-----------|
| **TOL-01** | Wzbudzenie `trigger_openai_generation` na wadliwej encji | - | Narzędzie zostaje pociągnięte na asynchroniczny zapis dla nieistniejącego obiektu (Np. `entity_type: "cat"`, `entity_id: 1`). | API proxy wywala wywołanie bazy zwracając do serwera klienta model MCP `isError: true` informujące Claude'a, że serwer docelowy odrzucił generację, bot na Froncie przesyła userowi asercje o złej prośbie. | P2 |
| **TOL-02** | Skrypt bazy w `check_job_status` po wyczyszczeniu Horizon | Baza po świeżej egzekucji z opcją Cache flush jobs. | Odczyt statystyk statusów. Zapytanie do postgres'a z niewidziejącym zleceniem id = `#8902` (błąd cache hit loss). | Kod łapie odpowiedzki i rzuca asercyjne `res.rows.length === 0`, renderując sztucznemu botowi JSON/Text: "Not found", ratując przed 500 w Node. | P3 |
| **TOL-03** | Wyłuskanie pliku log po poleceniu `moviemind://logs` | Plik na ścieżce ma ponad 10,000 linii. Zrzut całości zatkałby context window modelu Claude'a. | Wysłanie prośby przez użytkownika w chat o najnowszy error laravela. | Kod Node używający algorytmu (TODO do usprawnienia produkcyjnie!) "Pociąga strumieniem OSTATNIE 50 linijek pliku zamiast całości", oszczędzając okno kontekstu asystenta. | P2 |

---

### Cykl raportowania dla QA:
W każdym kroku instalacji, developer winien użyć programu udostępnionego przez organizację do sztucznego symulowania strumienia klienta deweloperskiego. Połączenie klienta symulacyjnego i serwisu Web można zbadać przy pomocy oficjalnego dedykowanego wbudowanego testu pakietów od Anthropic (Moce skryptowe z paczki `npm create @modelcontextprotocol/inspector`).
