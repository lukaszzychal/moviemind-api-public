# Testy E2E: instrukcja nagrywania w Playwright

> **Dla:** Developerzy, QA  
> **Zakres:** Testy E2E w `tests/e2e/` (Playwright, TypeScript)

---

## Uruchamianie testów E2E

Przed uruchomieniem `npm run test:e2e` uruchom stos z override’em E2E, żeby `APP_URL` zgadzał się z baseURL Playwrighta (`http://127.0.0.1:8000`):

```bash
docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d --force-recreate
```

Następnie uruchom testy:

```bash
npm run test:e2e
```

Przy ręcznym testowaniu (np. Codegen) otwieraj aplikację pod adresem `http://127.0.0.1:8000`.

---

## 1. Język i stos testów

Testy E2E są pisane w **TypeScript** (pliki `.spec.ts`) z użyciem **Playwright** (`@playwright/test`):

- **Lokalizacja:** `tests/e2e/specs/*.spec.ts`
- **Konfiguracja:** `tests/e2e/playwright.config.ts` (`baseURL: 'http://localhost:8000'`)
- **Uruchomienie:** `npm run test:e2e` (używa powyższej konfiguracji)

Nazwy testów i bloków w kodzie są po angielsku.

---

## 2. Nagrywanie scenariuszy w przeglądarce (Codegen)

Zamiast pisać kod testu ręcznie, można **nagrywać** czynności w przeglądarce – Playwright wygeneruje kod.

### 2.1 Wymagania

- Działająca aplikacja (np. `docker compose up -d`)
- Node.js i zainstalowane zależności (`npm install`)
- **Logowanie do panelu admina w testach:** Playwright używa `baseURL` (np. `http://127.0.0.1:8000`). Laravel używa `APP_URL` do przekierowań i ciasteczek. Gdy adresy się różnią, ciasteczko sesji nie jest wysyłane i logowanie w przeglądarce testowej się nie udaje. **Użyj osobnej konfiguracji E2E**, żeby nie zmieniać głównego `api/.env`:
  - **Zalecane:** Uruchom stos z override’em E2E: `docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d --force-recreate`. Użyj `--force-recreate`, żeby kontenery `php` i `horizon` przeładowały `APP_URL=http://127.0.0.1:8000`. Ręczne testy rób pod adresem `http://127.0.0.1:8000`.
  - **Cache konfiguracji:** Global-setup E2E uruchamia `config:clear`, dzięki czemu Laravel używa `APP_URL` z kontenera (a nie wartości z cache w `bootstrap/cache/config.php`). Jeśli nie uruchomisz aplikacji z override’em e2e, logowanie do panelu w testach może nadal się nie powieść.
  - **Opcjonalnie:** Jeśli potrzebujesz więcej zmiennych tylko na E2E, skopiuj `env/e2e.env.example` do `api/.env.e2e` i doładuj ten plik w override (np. w `docker-compose.e2e.yml`). Sekrety trzymaj w `.env`; w `.env.e2e` tylko nadpisania (np. `APP_URL`).

### 2.2 Uruchomienie nagrywarki

Z katalogu głównego projektu, z tą samą konfiguracją co w pozostałych testach E2E (ta sama `baseURL` itd.):

```bash
npx playwright codegen --config=tests/e2e/playwright.config.ts http://localhost:8000
```

Albo bez podawania URL (wtedy używana jest `baseURL` z configa):

```bash
npx playwright codegen --config=tests/e2e/playwright.config.ts
```

Otworzy się:

- okno przeglądarki (do interakcji),
- panel Playwright Inspector z generowanym kodem.

Wszystkie kliknięcia, wypełnienia i nawigacja są zapisywane jako wywołania API Playwright.

### 2.3 Co nagrywać

1. Opcjonalnie wejdź na `/admin/login` i zaloguj się (email, hasło, Sign in).
2. Wykonaj scenariusz, który chcesz przetestować (np. wejście w Feature Flags, przełączenie flagi, wejście w Movies, uruchomienie Generate AI).
3. Zatrzymaj nagrywanie i skopiuj wygenerowany kod z Inspectora.

### 2.4 Zapis wygenerowanego kodu

- W Inspectorze użyj **Copy** lub **Save** i zapisz wynik do nowego pliku w `tests/e2e/specs/`, np. `my-recorded.spec.ts`.

---

## 3. Zgodność nagranego testu z obecnym projektem

Nagrany kod **nie** zawiera przygotowania środowiska z tego projektu. Aby był spójny z istniejącymi specami (np. `admin-flags.spec.ts`):

### 3.1 Importy i struktura

Na początku pliku:

```ts
import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';
```

Nagrane kroki umieść w `test.describe` oraz w blokach `test('...', async ({ page }) => { ... })`.

### 3.2 Setup i logowanie (testy admina)

Dla flow po panelu admina dodaj ten sam cykl życia i logowanie co w innych specach:

- **beforeAll** (opcjonalnie): jednorazowe `test:prepare-e2e`.
- **beforeEach**: uruchomienie `test:prepare-e2e`, potem:
  - `page.goto('/admin/login', { waitUntil: 'networkidle' })`
  - wypełnienie email i hasła,
  - klik „Sign in”,
  - `await expect(page).toHaveURL(/\/admin\/?$/, { timeout: 25000 })`
  - oczekiwanie na element z dashboardu, np. `await expect(page.getByRole('link', { name: 'Movies' }).first()).toBeVisible({ timeout: 10000 })`

Przykład (pełny wzór w `tests/e2e/specs/admin-flags.spec.ts`):

```ts
test.beforeEach(async ({ page }) => {
  const projectRoot = path.resolve(__dirname, '../../..');
  execSync('docker compose exec -T php php artisan test:prepare-e2e', { stdio: 'pipe', cwd: projectRoot });
  await page.goto('/admin/login', { waitUntil: 'networkidle' });
  await page.getByLabel('Email address').fill('admin@moviemind.local');
  await page.getByLabel('Password').fill('password123');
  await Promise.all([
    page.waitForURL(/\/admin\/?$/, { timeout: 25000 }),
    page.getByRole('button', { name: 'Sign in' }).click(),
  ]);
  await expect(page).toHaveURL(/\/admin\/?$/, { timeout: 25000 });
  await expect(page.getByRole('link', { name: 'Movies' }).first()).toBeVisible({ timeout: 10000 });
});
```

Nagrane **kroki** wklej do testu (po logowaniu w `beforeEach`). Jeśli nagrałeś też logowanie, usuń z wklejki zduplikowane kroki logowania, tak aby test wykonywał tylko scenariusz po zalogowaniu.

### 3.3 Zastąpienie sleepy asercjami

Codegen często dodaje `page.waitForTimeout(…)`. Lepiej zastąpić je asercjami, np.:

- `await expect(page.getByRole('heading', { name: 'Feature Flags' })).toBeVisible({ timeout: 10000 });`
- `await expect(page).toHaveURL(/\/admin\/features$/);`

### 3.4 Spójne selektory

Preferuj `getByRole`, `getByLabel`, `getByText`. Jeśli Codegen wygenerował `locator('...')` z długimi ścieżkami CSS, uprość je tak, aby styl był zbliżony do pozostałych speców.

---

## 4. Edycja i naprawa nagranych scenariuszy

**Nie musisz** nagrywać testu od zera, gdy zmieniła się aplikacja albo test się wywala. Wystarczy edytować istniejący plik `.spec.ts`.

### 4.1 Gdy zmienił się UI lub flow

1. **Zaktualizuj selektory** – Jeśli zmienił się tekst przycisku, etykieta pola albo nagłówek, popraw odpowiednią linię w specie, np.:
   - `getByRole('button', { name: 'Sign in' })` → zmień `'Sign in'`, jeśli zmienił się tekst przycisku.
   - `getByLabel('Email address')` → zmień na nową etykietę, jeśli formularz został przemianowany.
   - `getByRole('heading', { name: 'Feature Flags' })` → zmień tekst nagłówka, jeśli zmienił się tytuł strony.
2. **Zaktualizuj URL-e** – Jeśli używasz `expect(page).toHaveURL(...)` albo `waitForURL(...)`, zmień regex lub string, żeby pasował do nowej ścieżki.
3. **Dodaj lub poluzuj oczekiwania** – Jeśli strona ładuje się wolniej albo treść pojawia się później, zwiększ `timeout` w `toBeVisible({ timeout: 15000 })` albo dodaj asercję przed kolejną akcją.

### 4.2 Gdy test się wywala (błąd lub timeout)

1. **Uruchom tylko ten test**, żeby szybko sprawdzać poprawki:
   ```bash
   npm run test:e2e -- tests/e2e/specs/admin-flags.spec.ts -g "should display feature flags"
   ```
2. **Przeczytaj błąd** – Playwright podaje plik, numer linii i zwykle treść nieudanej asercji (np. "Timeout waiting for locator", "Expected URL …").
3. **Otwórz raport HTML** po uruchomieniu, żeby zobaczyć zrzuty ekranu i trace dla nieudanego kroku:
   ```bash
   npx playwright show-report
   ```
4. **Popraw nieudaną linię** – Często chodzi o:
   - **Selector już nie pasuje** – Zmienił się tekst lub struktura elementu. Otwórz aplikację w przeglądarce, użyj **Pick locator** (w Playwright Inspector lub rozszerzeniu VS Code), wklej nowy selector i w specu zastąp stary stabilnym odpowiednikiem (`getByRole`, `getByLabel` albo `getByText`).
   - **Timeout** – Element pojawia się później albo akcja trwa dłużej. Zwiększ `timeout` w asercji albo dodaj wcześniej `await expect(...).toBeVisible({ timeout: ... })`.
   - **Zły URL** – Zmienił się redirect lub ścieżka. Zaktualizuj wzorzec w `toHaveURL` / `waitForURL`.

### 4.3 Ponowne nagranie tylko fragmentu scenariusza

Jeśli zmienił się cały kawałek flow (np. nowy modal albo krok), możesz nagrać tylko ten fragment i wkleić go do istniejącego speca:

1. Uruchom Codegen z configiem projektu: `npx playwright codegen --config=tests/e2e/playwright.config.ts`.
2. Ręcznie wejdź w to samo miejsce, od którego zaczyna się zmieniony flow (np. otwórz panel admina i przejdź do właściwej strony), albo użyj krótkiego skryptu z `page.goto(...)` i logowaniem, żeby zacząć we właściwym stanie.
3. Nagraj **tylko nowe lub zmienione kroki** w przeglądarce.
4. Skopiuj wygenerowane linie z Inspectora i **wklej w miejsce** odpowiedniego bloku w istniejącym specu (usuń stare kroki, wklej nowe).
5. Zastosuj tę samą „higienę” co w rozdziale 3: dodaj/zachowaj `test.describe` i setup, zamień `waitForTimeout` na asercje, uprość selektory do `getByRole` / `getByLabel` / `getByText`.

### 4.4 Podsumowanie

| Sytuacja | Co zrobić |
|----------|-----------|
| Zmienił się tekst przycisku/etykiety/nagłówka | Edytuj odpowiedni string w selektorze (name, label lub text). |
| Test wywala się na elemencie (timeout) | Zwiększ `timeout` albo dodaj oczekiwanie na widoczność przed akcją. |
| Zmienił się redirect lub URL | Zaktualizuj regex/string w `toHaveURL` / `waitForURL`. |
| Cały blok kroków jest nieaktualny | Nagraj ten blok w Codegen, wklej do speca, potem dopasuj do stylu (selektory, asercje). |
| Chcesz zobaczyć, dlaczego test nie przeszedł | Uruchom pojedynczy test, potem `npx playwright show-report` i otwórz trace/zrzut ekranu. |

---

## 5. Szybka ściąga

| Zadanie | Polecenie |
|--------|-----------|
| Uruchom aplikację pod E2E (APP_URL=127.0.0.1) | `docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d --force-recreate` |
| Uruchom wszystkie testy E2E | `npm run test:e2e` |
| Uruchom jeden spec | `npm run test:e2e -- tests/e2e/specs/admin-flags.spec.ts` |
| Uruchom jeden test po nazwie | `npm run test:e2e -- tests/e2e/specs/admin-flags.spec.ts -g "should display feature flags"` |
| Nagrywanie z configiem projektu | `npx playwright codegen --config=tests/e2e/playwright.config.ts` |
| Otwórz ostatni raport HTML | `npx playwright show-report` |

---

## 6. Powiązane dokumenty

- [Testy automatyczne (PHP + E2E)](../qa/AUTOMATED_TESTS.md) – struktura testów i polecenia
- [Konfiguracja Playwright](../../tests/e2e/playwright.config.ts) – baseURL, timeouty, projekty
- [Przykład E2E admina](../../tests/e2e/specs/admin-flags.spec.ts) – pełny wzór beforeEach + test
