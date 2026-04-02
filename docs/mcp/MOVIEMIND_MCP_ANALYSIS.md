# Analiza wdrożenia serwera MCP w MovieMind API

Ten dokument opisuje, jak MCP może działać w MovieMind i gdzie ma sens
jako narzędzie produktowe, a gdzie jako warstwa diagnostyczna.

## Cel

MovieMind może wykorzystać MCP na dwa sposoby:

1. jako warstwę dla chatbota lub asystenta użytkownika,
2. jako prywatne narzędzie dla programisty albo operatora,
3. jako cienką integrację między modelem AI a zasobami systemu.

W praktyce to nie musi być jeden publiczny serwer z pełnym dostępem do
wszystkiego. Taki układ szybko robi się niebezpieczny.

## Dwa główne zastosowania

### End-User MCP

To wariant dla użytkownika końcowego albo chatbota osadzonego we
frontendzie.

Typowy scenariusz:

Użytkownik pyta o filmy Nolana albo prosi o wygenerowanie nowego opisu.
Model nie zgaduje odpowiedzi z pamięci. Zamiast tego korzysta z MCP,
żeby pobrać dane i uruchomić bezpieczne akcje produktowe.

Ten wariant powinien mieć dostęp tylko do:

- wyszukiwania danych produktowych,
- ograniczonych akcji użytkownika,
- zasobów przydatnych w rozmowie z klientem.

Nie powinien mieć dostępu do:

- logów aplikacji,
- restartów kolejek,
- metryk operacyjnych,
- narzędzi administracyjnych.

### DevOps MCP

To wariant dla programisty, administratora albo operatora systemu.

Typowy scenariusz:

Twórca pyta, dlaczego job się wywalił albo czemu kolejka przestała
przetwarzać zadania. MCP pobiera logi, status joba albo metryki i daje
modelowi aktualny kontekst diagnostyczny.

Ten wariant powinien mieć dostęp do:

- logów,
- statusów jobów,
- kolejek,
- metryk operacyjnych,
- zasobów administracyjnych.

Nie powinien być publicznie wystawiany zwykłym użytkownikom.

## Rekomendowany podział w MovieMind

Najzdrowszy układ produkcyjny wygląda tak:

- `End-User MCP` jako osobny, ograniczony serwer albo osobny profil
  narzędzi,
- `DevOps MCP` jako prywatny serwer dostępny tylko dla zespołu.

To może działać jako dwa osobne serwery albo jako jeden kodbase z
przełącznikiem roli, na przykład `MCP_ROLE`.

## Macierz ról w obecnym `mcp-server`

Po wprowadzeniu `MCP_ROLE` jeden kod serwera może działać w dwóch
profilach.

### Tools

| Element | `end_user` | `devops` |
| --- | --- | --- |
| `generate_ai_description` | tak | tak |
| `search_database_movies` | tak | tak |
| `check_job_status` | tak | tak |
| `dispatch_job_retry` | nie | tak |
| `trigger_cache_clear` | nie | tak |

### Resources

| Element | `end_user` | `devops` |
| --- | --- | --- |
| `moviemind://database/schema-summary` | tak | tak |
| `moviemind://frontend/i18n-maps/pl` | tak | tak |
| `moviemind://logs/laravel-recent` | nie | tak |
| `moviemind://cache/horizon-metrics` | nie | tak |

### Prompts

| Element | `end_user` | `devops` |
| --- | --- | --- |
| `recommend_movies_by_actor` | tak | tak |
| `analyze_failed_generation` | nie | tak |
| `audit_translations_and_frontend` | nie | tak |

### Profile uruchomienia

- `MCP_ROLE=end_user` oznacza serwer użytkownika końcowego, bez logów i
  narzędzi operacyjnych.
- `MCP_ROLE=devops` oznacza serwer diagnostyczny dla zespołu, z
  zasobami i narzędziami administracyjnymi.

## Moduły MCP w MovieMind

MovieMind potrzebuje trzech podstawowych grup elementów: `tools`,
`resources` i `prompts`.

### Tools

Dla `End-User MCP` sens mają:

- `generate_ai_description`,
- `search_database_movies`,
- `check_job_status`.

Dla `DevOps MCP` sens mają dodatkowo:

- `dispatch_job_retry`,
- `trigger_cache_clear`.

### Resources

Dla `End-User MCP` sens mają:

- `moviemind://database/schema-summary`,
- `moviemind://frontend/i18n-maps/pl`.

Dla `DevOps MCP` sens mają dodatkowo:

- `moviemind://logs/laravel-recent`,
- `moviemind://cache/horizon-metrics`.

### Prompts

Dla `End-User MCP` sens ma przede wszystkim:

- `recommend_movies_by_actor`.

Dla `DevOps MCP` sens mają dodatkowo:

- `analyze_failed_generation`,
- `audit_translations_and_frontend`.

## Sposób implementacji

Są dwie rozsądne drogi.

### Ścieżka 1: integracja bezpośrednio w Laravel

To najbardziej spójny wariant, jeśli chcesz trzymać całą logikę po
jednej stronie.

Zalety:

- pełny dostęp do modeli, serwisów i polityk aplikacji,
- mniej duplikacji logiki,
- prostsza spójność z backendem.

Wady:

- trudniej odseparować warstwę MCP od głównej aplikacji,
- większy ciężar po stronie API,
- mniej elastyczne wdrożenie jako osobny serwis.

### Ścieżka 2: osobna mikrousługa TypeScript

To podejście jest bliższe obecnemu `mcp-server`.

Zalety:

- łatwiejszy deployment jako osobny serwis,
- dobre wsparcie SDK,
- prostsze rozdzielenie `end_user` i `devops`.

Wady:

- trzeba pilnować granicy między MCP a backendem,
- część logiki może się rozjechać, jeśli serwer zacznie omijać Laravel
  API,
- trzeba świadomie zarządzać autoryzacją i zakresem narzędzi.

## Jak uruchamiać to w MovieMind

### DevOps MCP lokalnie przez `stdio`

To najlepszy wariant do codziennej pracy programistycznej.

Pasuje do:

- logów,
- diagnostyki,
- statusów jobów,
- narzędzi operatorskich.

Zalety:

- brak publicznego portu HTTP,
- prostsze bezpieczeństwo,
- szybkie podpięcie do Cursor albo Claude Desktop.

Przykładowa konfiguracja klienta MCP:

```json
{
  "mcpServers": {
    "moviemind_devops_local": {
      "command": "node",
      "args": [
        "/absolute/path/to/moviemind-api-public/mcp-server/build/index.js"
      ],
      "env": {
        "MCP_ROLE": "devops",
        "TRANSPORT_TYPE": "stdio",
        "DB_HOST": "localhost",
        "DB_PORT": "5432",
        "DB_USERNAME": "your_user",
        "DB_PASSWORD": "your_password",
        "DB_DATABASE": "moviemind",
        "LARAVEL_API_URL": "http://localhost:8000/api/v1",
        "LARAVEL_API_KEY": "your_api_key"
      }
    }
  }
}
```

### DevOps MCP lokalnie w kontenerze

Ten wariant ma sens, jeśli zespół chce spójnego środowiska albo testów
zbliżonych do produkcji.

Pasuje do:

- lokalnego testowania `SSE`,
- integracji z bazą i usługami w kontenerach,
- pracy na wspólnym obrazie.

### End-User MCP jako kontener na Railway

To najlepszy wariant dla chatbota albo warstwy user-facing.

Taki serwer powinien:

- działać jako osobny serwis,
- używać `SSE`,
- mieć ograniczony zestaw narzędzi,
- pytać backend aplikacyjny przez API, zamiast mieć szeroki dostęp
  operacyjny.

Tu właśnie sens ma `LARAVEL_API_URL`, bo end-userowy MCP powinien
rozmawiać z backendem aplikacyjnym, a nie bezpośrednio z całym zapleczem
diagnostycznym.

Przykładowa konfiguracja klienta MCP dla zdalnego `SSE`:

```json
{
  "mcpServers": {
    "moviemind_end_user_remote": {
      "type": "sse",
      "url": "https://your-mcp-service.up.railway.app/sse",
      "headers": {
        "Authorization": "Bearer your_mcp_auth_token"
      }
    }
  }
}
```

## Czego nie mieszać

Nie warto wystawiać publicznie jednego serwera, który jednocześnie:

- pokazuje logi,
- restartuje joby,
- obsługuje użytkownika końcowego,
- ma dostęp do danych operacyjnych.

Taki układ jest do przeżycia jako PoC, ale słabo skaluje się jako
architektura produkcyjna.

## Rekomendacja końcowa

Docelowo MovieMind powinien iść w ten układ:

1. `DevOps MCP` uruchamiany lokalnie przez `stdio` albo prywatnie w
   kontenerze.
2. `End-User MCP` wdrażany jako osobny kontener na Railway.
3. Oba warianty korzystają z osobnych narzędzi, zasobów i polityk
   dostępu.
