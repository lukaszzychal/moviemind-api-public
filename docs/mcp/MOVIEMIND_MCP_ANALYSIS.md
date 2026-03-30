# 🧠 Analiza: Wdrożenie Serwera MCP w projekcie MovieMind API

Poniższy dokument analizuje zastosowanie standardu **Model Context Protocol (MCP)** z punktu widzenia aplikacji MovieMind.
Przedstawiono w nim logikę budowy dedykowanego asystenta deweloperskiego, który podpięłby wirtualną inteligencję do wewnętrznego krwiobiegu aplikacji by znacząco zrewolucjonizować pracę przy pisaniu kodu, debugowaniu i rozwiązywaniu błędów.

---

## 🎯 Cel wdrożenia
Zaprojektowanie i zainstalowanie opartego o TypeScript lub PHP serwera *MovieMind MCP*.
Programowy agent podpięty przez ten protokół pełnić będzie funkcję centralnego, zaawansowanego narzędzia wsparcia developerskiego (`DevOps Copilot`) dającego AI:
1. Natychmiastowy i bezbłędny dostęp merytoryczny do struktur bazowych `PostgreSQL` dla bazy danych filmoznawczej.
2. Możliwość ręcznego operowania asynchronicznymi zadaniami w kolejkach na `Redis/Laravel Horizon`.
3. Pasywną analizę logów systemowych pod kątem failów API czy błędów walidacyjnych oraz tłumaczeń interfejsowych w locie.

Dzięki integracji MCP poprzez Cursor IDE czy aplikację okienkową, praca programisty z generowaniem kolejnych funkcjonalności staje się niesamowicie przyspieszona.

---

## 📄 Główne Przeznaczenie (Praktyczne Use-Case'y)

Choć MCP najczęściej kojarzone jest z pomocą dla programistów, może napędzać główne moduły "rozmowy" dla zwykłych użytkowników na Twojej docelowej platformie VOD! Rozbijmy to na dwa kluczowe nurty.

### 1. "End-User MCP" - Asystent dla klientów MovieMind (ZALECANE)
Głównym celem powołania MCP jest to, by asystent (bot na Twojej stronie Vue.js) mógł rozmawiać z normalnymi ludźmi o filmach, samemu wywołując akcje. 
Użytkownik na serwisie wpisuje: *"Wypisz mi filmy Nolana, a jeśli ich nie macie, poproś by aplikacja wygenerowała dla mnie biografię tego twórcy"*.
**Zamiast uczyć asystenta na pamięć całej bazy, serwer MCP sam mu to podaje. Bot komunikując się przez Twój Serwer MCP:**
- Sam odwołuje się do API MovieMind by wyszukać filmy Nolana.
- Używa Twojego `Narzędzia` o nazwie `trigger_openai_generation`, zlecając backendowi Laravel wygenerowanie danych z tła.
- Przez `Zasoby` potrafi odczytać najciekawsze nowości kinowe zapisane w Twoim redisie i wpleść je do odpowiedzi na czacie z gościem.

### 2. "DevOps MCP" - Asystent diagnostyczny (Dla Ciebie)
Scenariusz poboczny (omawiany wcześniej): jako twórca logujesz się np. do Cursora czy Claude Desktop. Mówisz *"Dlaczego plakat spadł z bazy?"*, a MCP pobiera dla asystenta aktualne logi błędów z systemu (`laravel.log`) i szuka dla Ciebie usterki by skrócić proces naprawiania.

---

## 🧩 Moduły wsparcia architektonicznego MCP dla MovieMind

By wdrożyć takie wsparcie i uszyć je konkretnie pod aplikację, projekt uwzględni 3 kluczowe komponenty środowiska definiowane przez protokół dla klienta. Zostały one zdefiniowane i przypisane do odpowiednich ról (End-User MCP lub DevOps MCP).

### 1. ⚙️ Narzędzia (Tools) – Moduł Zadaniowy i Manipulacji 
Asystent AI dostanie zestaw "Rąk wykonawczych", wywoływalnych poprzez serwer. Wspólna pula rozdzielona na zadania:

**Dla Czatbota Klienta (End-User):**
*   `generate_ai_description`: Tworzy nowy wpis asynchroniczny i wysyła Job w tło na serwer OpenAI (np. gdy użytkownik żąda informacji spoza bazy). *Parametry: `entity_type` (np. MOVIE), `entity_id` (np. 15), `locale` (np. pl-PL), `context_tag`.*
*   `search_database_movies`: Pozwala modelowi AI odpytać tabele, jeżeli w poleceniu znajduje się naturalny wpis tekstowy np. tytuł filmu w poszukiwaniu unikalnego ID w chęci zaprezentowania danych rozmówcy.

**Dla Asystenta Diagnostycznego (DevOps):**
*   `check_job_status`: Model sprawdza sam status wysłanego zlecenia podając określone `job_id`. Pyta `AiJob` Eloquenta czy status wynosi *PENDING/DONE/FAILED*.
*   `trigger_cache_clear`: Implementacja metody umożliwiającej botowi czyszczenie zdefiniowanych kluczy z cache'u Redis po zakończonym usuwaniu awarii.
*   `dispatch_job_retry`: Manualne podpięcie AI do systemu komend Aritsana np. `php artisan queue:retry`, które pozwala AI restartować sfailowane eventy bez Twojego wychodzenia ze środowiska dialogowego.


### 2. 🗂️ Zasoby (Resources) – Moduł Dynamicznego Kontekstu
Zasoby, z których model AI może sam czerpać potrzebne dane w trybie Real-Time, udostępniane serwerem po lokalnych protokołowych ścieżkach URI:

**Dla Czatbota Klienta (End-User):**
*   `moviemind://database/schema-summary`: Dynamicznie dostarcza asystentowi zbiór wszystkich tabel bazodanowych oraz powiązań The Movie Database (TMDb), ułatwiając mu podbudowę logiki w odpisywaniu ludziom o nowościach.
*   `moviemind://frontend/i18n-maps/{lang}`: Zasób z JSON'ami tłumaczeń z Frontendu (np. pl/en/de). Bot ma do nich stały i dynamiczny dostęp sprawdzając w jakim języku załadować klientowi polecane produkcje.

**Dla Asystenta Diagnostycznego (DevOps):**
*   `moviemind://logs/laravel-recent`: Bieżąca zawartość ogona pliku ze ścieżki `storage/logs/laravel.log`, od której system rozpoczynałby proces automatycznego diagnozowania kodu podczas pisania.
*   `moviemind://cache/horizon-metrics`: Zrzut logów ze statystyk okna obciążenia Laravel Horizon.


### 3. 🤔 Prompty (Prompts) – Moduł Startowy Analityki
Pre-konfigurowane gotowce dla ułatwionego wchodzenia bota w interakcje.

**Dla Czatbota Klienta (End-User):**
*   `recommend_movies_by_actor`: 
    *   **Założenie:** "Zaproponuj użytkownikowi najnowszą kolekcję filmów o zadanej tematyce."
    *   **Reakcja serwera:** Serwer podłącza w locie historię użytkownika (Zasoby) i aktywuje u asystenta gotowość do dyskusji przy zadaniu odpowiednich pytań w lejek.

**Dla Asystenta Diagnostycznego (DevOps):**
*   `analyze_failed_generation`: 
    *   **Założenie:** "Przeanalizuj problem generowania opisów do encji filmowych i znajdź przyczynę awarii w Jobs'ach."
    *   **Reakcja serwera:** Serwer podłącza w locie logi błędów z systemu do wiadomości bazowej (kontekst zasobu `moviemind://logs`). Podpowiada za użytkownika powody timeoutów HTTP / złego formatu OpenAI.
*   `audit_translations_and_frontend`:
    *   **Założenie:** "Sprawdź braki słów lub kluczy pomiędzy słownikiem DE, a EN front-endu."
    *   **Reakcja serwera:** Natychmiastowo importuje do asystenta najnowsze drzewa plików JSON projektów VUE i analizuje odróżnienia semantyczne lub pustostany.

---

## 🏗️ Sposób Implementacji (Jak to fizycznie napisać?)

Rozwiązanie to musi zostać zintegrowane bezpośrednio z architekturą Twojego środowiska. Poniżej przedstawiamy, jak dokładnie wyglądałaby techniczna instalacja i parametryzacja kodu w dwóch wytyczonych scenariuszach wdrożeniowych.

### Ścieżka 1: Bezpośrednia Integracja do jądra Laravel (PHP)
Najbardziej spójne rozwiązanie zakłada wbudowanie protokołu MCP prosto w MovieMind API, rezygnując z technologii pobocznych jak NodeJS. 
- **Biblioteka:** Wykorzystanie wschodzących portów protokołu dla języka PHP (bądź prosta implementacja kontrolera Laravel na bazie standardu JSON-RPC 2.0). 
- **Zasada instalacji kodu:** 
  1. Budujesz nową komendę `php artisan mcp:serve` (dla nasłuchu I/O z konsoli) oraz dedykowany `McpController.php` (dla nasłuchu zdalnego klienta po webowym HTTP w `routes/api.php`).
  2. Narzędzia MCP w kodzie PHP to zwykłe funkcje/klasy Action, odwołujące się do Fasady: `AiJob::all()`, `Movie::search()`, `Horizon::getWorkload()`.
- **Jak postawić to na Dockerze? (Lokalne środowisko developerskie):** Serwer MCP dołącza po prostu jako kolejna z usług do Twojego pliku `compose.yml`, wykonując komendę artisan przy bieżącym starcie kontenera w tle (rozmawiając na paśmie STDIO z systemem gospodarza). Aby połączyć Twojego instalowanego na PC Claude'a, podpinasz mu wprost komendę polecającą: `"command": "docker", "args": ["exec", "-i", "moviemind_backend", "php", "artisan", "mcp:serve"]`.
- **Zalety PHP:** Totalny dostęp prosto z wnętrza do bazy danych bez tworzenia drugiego mikro-serwisu. Idealne, aby zainicjować "Inteligentny Asystent dla Użytkownika na Froncie Vue.js" i odpytywać ten mechanizm wewnętrznym kluczem API po Opcji Zdalnej.

### Ścieżka 2: Mikrousługa TypeScript (Node.js) jako satelita projektu
Alternatywa opierająca się o środowisko stworzone od zera specjalnie pod framework Node, zalecana głównie przez Anthropic (z racji wybitnego oficjalnego wsparcia).
- **Biblioteka:** Rozbudowane i wspierane oficjalnie SDK `@modelcontextprotocol/sdk`. 
- **Zasada instalacji kodu:** 
  1. Stawiasz mini katalog `mcp-ts-server`, uruchamiasz w nim `npm init`.
  2. Piszesz skrypt, który używając wbudowanych bibliotek PostgreSQL (np. `pg` czy ORM'u `Prisma`) łączy się oddzielnie, ze swoimi danymi logowania z Twoją wirtualną bazą danych PG. Potrafi rónież pojechać po klasycznym REST HTTP do Twoich endpointów w Laravelu (`api/v1/jobs/check`). 
- **Jak postawić to na Chmurze? (Railway / Opcja Zdalna):** Przerzucasz paczkę `mcp-ts-server` na platformę Railway i hostujesz jako niezależny nowy mini-serwis (nowy kafel w panelu platformy, osobny routing). Skrypt w środku inicjuje natywny moduł `SSEServerTransport` udostępniający serwer web HTTPS. Apka uruchamia się raz i działa 24/7 po publicznej ukształtowanej ścieżce url. 
- **Konfiguracja bezpieczeństwa! (Ważne):** Twoja webowa trasa musi być restrykcyjnie zabezpieczona mocnymi zaszyfrowanymi tokenami (Bearer), bądź kluczem API, ograniczając wjazd. Twój np. Front-End po nawiązaniu HTTP z URL i wklepaniu ukrytego Tokena odblokowuje dla asystenta połączenie i czerpie obiekty JSON do renderu chatu.
- **Zalety JS i separacji:** Nie powiększasz rozmiaru głównego gniazda serwera PHP. Wyciągasz go w osobny odseparowany wirtualny "box". Idealne dla tworzenia potężnych prywatnych asystentów diagnostycznych potrafiących po API zwinnie monitorować dziesiątki frameworków wokół Moviemind.
