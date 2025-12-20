# Konfiguracja MCP File System Server

Ten dokument opisuje jak skonfigurować MCP File System Server do użycia z Cursor lub Claude Desktop.

## Przegląd

[MCP File System Server](https://github.com/MarcusJellinghaus/mcp_server_filesystem) to bezpieczny serwer Model Context Protocol, który zapewnia operacje na plikach dla asystentów AI. Umożliwia Claude i innym asystentom bezpieczne odczytywanie, zapisywanie i listowanie plików w określonym katalogu projektu z solidną walidacją ścieżek i kontrolami bezpieczeństwa.

## Instalacja

### Wymagania

- Python 3.8 lub nowszy
- pip (menedżer pakietów Python)

### Instalacja serwera

```bash
pip install git+https://github.com/MarcusJellinghaus/mcp_server_filesystem.git
```

Lub zainstaluj konkretną wersję:

```bash
pip install git+https://github.com/MarcusJellinghaus/mcp_server_filesystem.git@0.1.1
```

## Konfiguracja

### Dla Cursor IDE

Utwórz lub edytuj plik konfiguracyjny MCP. Lokalizacja zależy od systemu:

**macOS:**
```bash
~/.cursor/mcp.json
```

**Linux:**
```bash
~/.config/cursor/mcp.json
```

**Windows:**
```bash
%APPDATA%\Cursor\mcp.json
```

### Opcje konfiguracji

Masz kilka opcji uruchamiania serwera MCP. Wybierz tę, która najlepiej działa w Twoim środowisku:

#### Opcja 1: Użycie `uvx` (wymaga menedżera pakietów `uv`)

Najpierw zainstaluj `uv`:
```bash
# macOS/Linux
curl -LsSf https://astral.sh/uv/install.sh | sh

# Lub przez pip
pip install uv
```

Następnie użyj tej konfiguracji:

```json
{
  "mcpServers": {
    "filesystem": {
      "command": "uvx",
      "args": [
        "mcp-server-filesystem",
        "--project-dir",
        "/Users/lukaszzychal/PhpstormProjects/moviemind-api-public"
      ]
    }
  }
}
```

#### Opcja 2: Użycie modułu Python (zalecane, jeśli `uvx` nie jest dostępne)

Po zainstalowaniu pakietu przez `pip`, użyj tej konfiguracji:

```json
{
  "mcpServers": {
    "filesystem": {
      "command": "python",
      "args": [
        "-m",
        "mcp_server_filesystem",
        "--project-dir",
        "/Users/lukaszzychal/PhpstormProjects/moviemind-api-public"
      ]
    }
  }
}
```

**Uwaga:** Jeśli używasz `python3` zamiast `python`, zamień `"python"` na `"python3"` w konfiguracji.

#### Opcja 3: Użycie pełnej ścieżki do interpretera Python

Jeśli musisz określić dokładny interpreter Python:

```json
{
  "mcpServers": {
    "filesystem": {
      "command": "/usr/bin/python3",
      "args": [
        "-m",
        "mcp_server_filesystem",
        "--project-dir",
        "/Users/lukaszzychal/PhpstormProjects/moviemind-api-public"
      ]
    }
  }
}
```

**Uwaga:** Zamień `/Users/lukaszzychal/PhpstormProjects/moviemind-api-public` na rzeczywistą ścieżkę do projektu we wszystkich konfiguracjach.

### Dla Claude Desktop

**macOS:**
```bash
~/Library/Application Support/Claude/claude_desktop_config.json
```

**Windows:**
```bash
%APPDATA%\Claude\claude_desktop_config.json
```

**Linux:**
```bash
~/.config/Claude/claude_desktop_config.json
```

Użyj tych samych opcji konfiguracji co dla Cursor IDE (patrz powyżej). Zalecana opcja to **Opcja 2: Użycie modułu Python**, jeśli nie masz zainstalowanego `uv`:

```json
{
  "mcpServers": {
    "filesystem": {
      "command": "python",
      "args": [
        "-m",
        "mcp_server_filesystem",
        "--project-dir",
        "/Users/lukaszzychal/PhpstormProjects/moviemind-api-public"
      ]
    }
  }
}
```

## Alternatywa: Użycie narzędzia mcp-config

Dla łatwiejszej konfiguracji możesz użyć narzędzia deweloperskiego `mcp-config`:

### Instalacja mcp-config

```bash
pip install git+https://github.com/MarcusJellinghaus/mcp-config.git
```

### Szybka konfiguracja

```bash
# Konfiguracja dla Claude Desktop z automatyczną konfiguracją
mcp-config setup mcp-server-filesystem "Filesystem Server" \
  --project-dir /Users/lukaszzychal/PhpstormProjects/moviemind-api-public
```

### Konfiguracja z projektami referencyjnymi

```bash
mcp-config setup mcp-server-filesystem "Filesystem Server" \
  --project-dir /Users/lukaszzychal/PhpstormProjects/moviemind-api-public \
  --reference-project docs=/path/to/documentation \
  --reference-project examples=/path/to/examples
```

### Konfiguracja z niestandardowym logowaniem

```bash
mcp-config setup mcp-server-filesystem "Filesystem Server" \
  --project-dir /Users/lukaszzychal/PhpstormProjects/moviemind-api-public \
  --log-level DEBUG \
  --log-file /custom/path/server.log
```

## Dostępne narzędzia

Po skonfigurowaniu, MCP File System Server zapewnia następujące narzędzia:

### Operacje na plikach

- **`read_file`** - Odczyt zawartości pliku
- **`write_file`** - Zapisz lub utwórz pliki
- **`list_directory`** - Lista plików i katalogów
- **`delete_file`** - Usuń pliki
- **`edit_file`** - Edytuj pliki z zamianą tekstu
- **`move_file`** - Przenieś lub zmień nazwę plików

### Projekty referencyjne

- **`get_reference_projects`** - Odkryj dostępne projekty referencyjne
- **`list_reference_directory`** - Lista plików w projektach referencyjnych
- **`read_reference_file`** - Odczyt plików z projektów referencyjnych

## Funkcje bezpieczeństwa

- Wszystkie ścieżki są normalizowane i walidowane, aby zapewnić pozostanie w katalogu projektu
- Ataki path traversal są blokowane
- Pliki są zapisywane atomowo, aby zapobiec uszkodzeniu danych
- Operacje usuwania są ograniczone do katalogu projektu
- Projekty referencyjne są ściśle tylko do odczytu

## Rozwiązywanie problemów

### Serwer nie uruchamia się

1. Sprawdź czy Python jest zainstalowany: `python --version` lub `python3 --version`
2. Sprawdź czy pakiet jest zainstalowany: `pip list | grep mcp-server-filesystem`
3. Sprawdź składnię pliku konfiguracyjnego (poprawny JSON)
4. Sprawdź logi pod kątem komunikatów o błędach

### Błąd: "spawn uvx ENOENT" lub "uvx not found"

Ten błąd oznacza, że `uvx` nie jest zainstalowany lub nie znajduje się w PATH. **Rozwiązanie:** Użyj Opcji 2 (moduł Python) zamiast tego:

```json
{
  "mcpServers": {
    "filesystem": {
      "command": "python",
      "args": [
        "-m",
        "mcp_server_filesystem",
        "--project-dir",
        "/Users/lukaszzychal/PhpstormProjects/moviemind-api-public"
      ]
    }
  }
}
```

Lub najpierw zainstaluj `uv`:
```bash
# macOS/Linux
curl -LsSf https://astral.sh/uv/install.sh | sh

# Lub przez pip
pip install uv
```

### Błąd: "No module named mcp_server_filesystem"

To oznacza, że pakiet nie jest zainstalowany. **Rozwiązanie:** Zainstaluj go:
```bash
pip install git+https://github.com/MarcusJellinghaus/mcp_server_filesystem.git
```

### Testowanie instalacji

Możesz przetestować, czy serwer działa, uruchamiając go ręcznie:

```bash
python -m mcp_server_filesystem --project-dir /Users/lukaszzychal/PhpstormProjects/moviemind-api-public
```

Jeśli to działa, konfiguracja MCP również powinna działać.

### Problemy z uprawnieniami

- Upewnij się, że ścieżka do katalogu projektu jest poprawna i dostępna
- Na macOS/Linux upewnij się, że ścieżka ma odpowiednie uprawnienia do odczytu/zapisu
- Sprawdź, czy użytkownik uruchamiający Cursor/Claude Desktop ma dostęp do katalogu

### Problemy ze ścieżkami

- Używaj bezwzględnych ścieżek w konfiguracji
- Na Windows używaj ukośników do przodu lub escapowanych backslashy: `C:\\Users\\...`
- Unikaj używania `~` lub względnych ścieżek w konfiguracji

## Przykład użycia

Po skonfigurowaniu możesz używać serwera MCP w Cursor lub Claude Desktop:

```
Przeczytaj plik README.md
Lista plików w katalogu docs
Utwórz nowy plik test.txt z zawartością "Hello World"
```

Asystent AI użyje narzędzi MCP File System Server do wykonania tych operacji bezpiecznie w obrębie katalogu projektu.

## Referencje

- [MCP File System Server GitHub](https://github.com/MarcusJellinghaus/mcp_server_filesystem)
- [Narzędzie konfiguracyjne MCP](https://github.com/MarcusJellinghaus/mcp-config)
- [Dokumentacja Model Context Protocol](https://modelcontextprotocol.io)

## Licencja

MCP File System Server jest licencjonowany na licencji MIT.

