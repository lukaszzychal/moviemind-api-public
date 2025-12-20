# Strategia Zarządzania Serwerami MCP

## Przegląd

Ten dokument przedstawia strategię zarządzania serwerami MCP w Cursor IDE w celu optymalizacji wydajności i utrzymania limitu 80 narzędzi.

**Aktualny Status:** 84 narzędzia (przekroczony limit o 4 narzędzia)

## Kategorie Serwerów

### 🔴 Serwery Podstawowe (Zawsze Włączone)

Te serwery są niezbędne do codziennej pracy deweloperskiej i powinny być zawsze włączone:

| Serwer | Cel | Liczba Narzędzi | Priorytet |
|--------|------|------------------|-----------|
| **filesystem** | Operacje na plikach, zarządzanie projektem | ~5-10 | Krytyczny |
| **github** | Zarządzanie repozytoriami, issues, PR | ~15-20 | Krytyczny |
| **sequential-thinking** | Lepsze rozumowanie AI, rozwiązywanie problemów | ~1-2 | Wysoki |

**Łącznie Narzędzi Podstawowych:** ~21-32 narzędzia

### 🟡 Serwery Opcjonalne (Włączaj Gdy Potrzebne)

Te serwery powinny być włączane tylko wtedy, gdy aktywnie korzystasz z ich funkcji:

| Serwer | Cel | Kiedy Włączyć | Liczba Narzędzi |
|--------|------|---------------|-----------------|
| **postgres** | Dostęp do bazy danych, zapytania | Gdy wykonujesz zapytania do bazy | ~2-3 |
| **Chrome DevTools** | Debugowanie przeglądarki | Gdy debugujesz aplikacje web | ~10-15 |
| **Railway** | Deployment | Gdy wdrażasz na Railway | ~5-10 |
| **mcp-doc-generator** | Generowanie dokumentacji | Gdy generujesz dokumentację | ~3-5 |
| **firecrawl-mcp** | Web scraping | Gdy scrapujesz strony | ~5-10 |
| **memory-bank** | Zaawansowane przechowywanie wiedzy, RAG, embeddings | Gdy budujesz bazę wiedzy lub używasz RAG | ~3-5 |
| **playwright** | Automatyzacja przeglądarki | Gdy automatyzujesz przeglądarki | ~10-15 |
| **notion** | Integracja z Notion | Gdy używasz Notion do dokumentacji | ~10-15 |
| **docker** | Zarządzanie kontenerami | Gdy zarządzasz kontenerami Docker | ~8-12 |
| **postman** | Testowanie API | Gdy testujesz API | ~20-30 |

## Rekomendowana Konfiguracja

### Minimalna Konfiguracja (Zawsze Włączona)

Do codziennej pracy deweloperskiej, włącz tylko serwery podstawowe:

```json
{
  "mcpServers": {
    "filesystem": { ... },
    "github": { ... },
    "sequential-thinking": { ... }
  }
}
```

**Szacowana Liczba Narzędzi:** ~21-32 narzędzia (znacznie poniżej limitu 80)

### Pełna Konfiguracja (Gdy Potrzebna)

Włącz dodatkowe serwery w zależności od aktualnego zadania:

- **Praca z Bazą Danych:** Włącz `postgres`
- **Rozwój Web:** Włącz `playwright`, `Chrome DevTools`
- **Rozwój API:** Włącz `postman`
- **Dokumentacja:** Włącz `mcp-doc-generator`, `notion`
- **Deployment:** Włącz `Railway`, `docker`
- **Web Scraping:** Włącz `firecrawl-mcp`
- **Baza Wiedzy / RAG:** Włącz `memory-bank` (dla zaawansowanego przechowywania wiedzy)

## Przewodnik Szybkiego Włączania/Wyłączania

### Jak Wyłączyć Serwer w Cursor

1. Otwórz Ustawienia Cursor → Tools & MCP
2. Znajdź serwer w "Installed MCP Servers"
3. Kliknij przełącznik, aby go wyłączyć
4. Zrestartuj Cursor jeśli potrzeba

### Jak Włączyć Serwer

1. Otwórz Ustawienia Cursor → Tools & MCP
2. Znajdź serwer w "Installed MCP Servers"
3. Kliknij przełącznik, aby go włączyć
4. Zrestartuj Cursor jeśli potrzeba

## Zarządzanie Liczbą Narzędzi

### Aktualny Rozkład Narzędzi (Szacunkowy)

- Serwery podstawowe: ~25 narzędzi
- Serwery opcjonalne: ~59 narzędzi
- **Łącznie:** ~84 narzędzia (przekroczony limit)

### Docelowa Konfiguracja

- **Minimalna (codzienna praca):** ~25 narzędzi (tylko podstawowe)
- **Praca z Bazą Danych:** ~28 narzędzi (podstawowe + postgres)
- **Rozwój Web:** ~50 narzędzi (podstawowe + playwright + Chrome DevTools)
- **Rozwój API:** ~60 narzędzi (podstawowe + postman)
- **Full stack:** ~75 narzędzi (podstawowe + wiele opcjonalnych)

## Najlepsze Praktyki

1. **Zacznij od minimalnej konfiguracji** - Włącz tylko serwery podstawowe
2. **Włączaj na żądanie** - Dodawaj serwery gdy ich potrzebujesz
3. **Wyłączaj po użyciu** - Wyłączaj serwery gdy skończysz
4. **Monitoruj liczbę narzędzi** - Sprawdzaj ustawienia Cursor regularnie
5. **Grupuj według zadania** - Włączaj powiązane serwery razem

## Uwagi Specyficzne dla Serwerów

### PostgreSQL MCP (DBHub)
- Wymaga connection string do PostgreSQL
- Skonfigurowany dla lokalnej bazy: `postgresql://moviemind:moviemind@localhost:5432/moviemind`
- Włączaj tylko gdy wykonujesz zapytania do bazy lub analizujesz schemat
- Bardzo oszczędny w tokenach (~80% redukcji w obciążeniu zapytaniami)

### Notion MCP
- Wymaga zmiennej środowiskowej `NOTION_TOKEN`
- Pobierz token z: https://www.notion.so/profile/integrations
- Włączaj tylko gdy używasz Notion do dokumentacji

### Docker MCP
- Wymaga Docker Desktop lub Docker Engine **w stanie uruchomionym**
- Włączaj tylko gdy zarządzasz kontenerami
- Może być zasobożerny
- **Rozwiązywanie problemów:** Jeśli widzisz błąd "Failed to connect to Docker daemon":
  - Upewnij się, że Docker Desktop jest uruchomiony (sprawdź Aplikacje lub pasek systemowy)
  - Na macOS, Docker Desktop musi być uruchomiony przed użyciem Docker MCP
  - Jeśli Docker nie jest potrzebny, wyłącz serwer Docker MCP w ustawieniach Cursor

### Postman MCP
- Wymaga zmiennej środowiskowej `POSTMAN_API_KEY`
- Pobierz klucz z: https://postman.postman.co/settings/me/api-keys
- Włączaj tylko gdy testujesz API
- Ma wiele trybów: minimal, full, code

### Memory Bank MCP
- **Co to jest:** Zaawansowany system przechowywania wiedzy (nie to samo co zwykła pamięć AI)
- **Zwykła Pamięć AI:** Zawsze aktywna - AI pamięta kontekst w bieżącej sesji czatu
- **Memory Bank MCP:** Opcjonalne - przechowuje strukturę wiedzy o projekcie (knowledge graphs, embeddings, RAG) w plikach
- **Kiedy używać:**
  - Budowanie bazy wiedzy dla projektu
  - Używanie RAG (Retrieval-Augmented Generation) dla lepszego wyszukiwania kontekstu
  - Przechowywanie wiedzy specyficznej dla projektu, która powinna przetrwać między sesjami
  - Praca z dużymi codebase, gdzie potrzebujesz semantycznego wyszukiwania
- **Kiedy NIE używać:**
  - Proste projekty, które nie potrzebują zaawansowanego przechowywania wiedzy
  - Zwykłe zadania programistyczne (wbudowana pamięć AI jest wystarczająca)
  - Gdy chcesz zminimalizować liczbę narzędzi
- **Uwaga:** Zwykła pamięć AI w Cursor jest zawsze aktywna i pamięta kontekst rozmowy. Memory Bank MCP to dodatkowa, zaawansowana funkcja do strukturyzowanego przechowywania wiedzy.

## Rozwiązywanie Problemów

### Ostrzeżenie "Exceeding total tools limit"

**Rozwiązanie:** Wyłącz opcjonalne serwery, z których aktualnie nie korzystasz.

**Szybka naprawa:**
1. Wyłącz `postgres` (jeśli nie wykonujesz zapytań do bazy)
2. Wyłącz `Chrome DevTools` (jeśli nie debugujesz)
3. Wyłącz `Railway` (jeśli nie wdrażasz)
4. Wyłącz `firecrawl-mcp` (jeśli nie scrapujesz)
5. Wyłącz `memory-bank` (jeśli nie budujesz bazy wiedzy lub nie używasz RAG)

### Problemy z Wydajnością

Jeśli Cursor jest wolny:
1. Sprawdź liczbę narzędzi w ustawieniach
2. Wyłącz nieużywane serwery
3. Zrestartuj Cursor
4. Monitoruj zasoby systemowe

## Przewodnik Migracji

### Z Pełnej Konfiguracji do Minimalnej

1. Zanotuj, które serwery używasz regularnie
2. Wyłącz serwery, z których korzystasz rzadziej niż raz w tygodniu
3. Zostaw włączone tylko serwery podstawowe
4. Włączaj inne na żądanie

### Z Minimalnej do Specyficznej dla Zadania

1. Zidentyfikuj aktualne zadanie (web dev, testowanie API, etc.)
2. Włącz odpowiednie serwery dla tego zadania
3. Wyłącz gdy zadanie jest zakończone
4. Wróć do minimalnej konfiguracji

## Referencje

- [Dokumentacja Cursor MCP](https://cursor.com/docs/mcp)
- [Specyfikacja Model Context Protocol](https://spec.modelcontextprotocol.io/)
- [Katalog Serwerów MCP](https://github.com/modelcontextprotocol/servers)

