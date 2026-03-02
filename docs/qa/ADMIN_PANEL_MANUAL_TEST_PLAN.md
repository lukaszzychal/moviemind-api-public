# QA Manual Test Plan - Admin Panel (TASK-009)

**Cel:** Weryfikacja funkcjonalności i poprawności działania panelu admina.
**Środowisko:** Docker (Lokalne)
**URL:** `http://localhost:8000/admin`

---

### 0. Przygotowanie Danych (Wymagane przed testami)

Aby testy miały sens, baza danych musi zawierać użytkownika admina oraz przykładowe dane.

**Wykonaj w terminalu:**
```bash
# 1. Wyczyść i przygotuj bazę (UWAGA: Usunie istniejące dane!)
docker compose exec php php artisan migrate:fresh --seed

# 2. Upewnij się, że admin istnieje (jeśli nie użyłeś migrate:fresh --seed)
docker compose exec php php artisan db:seed --class=AdminUserSeeder
```

**Dane logowania:**
*   **Email:** `admin@moviemind.local`
*   **Hasło:** `password123`

---

### 1. Dostęp i Logowanie

| Krok | Akcja | Oczekiwany Rezultat | Status |
| :--- | :---- | :------------------ | :----: |
| 1.1 | Otwórz `http://localhost:8000/admin` | Przekierowanie na `http://localhost:8000/admin/login` (z zachowaniem portu!). | ☐ |
| 1.2 | Wpisz błędne dane (np. `test@test.com` / `test`) | Komunikat "These credentials do not match our records." | ☐ |
| 1.3 | Wpisz poprawne dane (`admin@moviemind.local` / `password123`) | Zalogowanie i przekierowanie na `http://localhost:8000/admin`. | ☐ |
| 1.4 | Wyloguj się (menu w prawym górnym rogu) | Przekierowanie na `http://localhost:8000/admin/login`. | ☐ |

---

### 2. Dashboard (Pulpit Główny)

| Krok | Akcja | Oczekiwany Rezultat | Status |
| :--- | :---- | :------------------ | :----: |
| 2.1 | Sprawdź widgety `StatsOverview` | Widoczne są kafelki: Total Movies, People, TV Series, TV Shows, Pending Jobs, Failed Jobs. Liczby powinny być > 0 (dzięki seederom). | ☐ |
| 2.2 | Sprawdź widget `JobsChart` | Wykres "Jobs by Queue" jest widoczny. | ☐ |
| 2.3 | Sprawdź widget `RecentJobsWidget` | Tabela "Recent Jobs" jest widoczna. | ☐ |
| 2.4 | Sprawdź widget `FailedJobsWidget` | Tabela "Failed Jobs" jest widoczna. | ☐ |

---

### 3. Zarządzanie Zasobami (Movies, People, TV)

Dla każdego zasobu (Movies, People, TV Series, TV Shows):

| Krok | Akcja | Oczekiwany Rezultat | Status |
| :--- | :---- | :------------------ | :----: |
| 3.1 | Wejdź na listę zasobów (np. `/admin/movies`) | Tabela z danymi ładuje się poprawnie. Widoczne są przykładowe filmy (np. "The Matrix"). | ☐ |
| 3.2 | Sprawdź nowe kolumny | Widoczne są kolumny: `Descriptions` (liczba) i `TMDb` (link). | ☐ |
| 3.3 | Kliknij link w kolumnie `TMDb` | Otwiera się nowa karta z odpowiednią stroną w serwisie TMDb. | ☐ |
| 3.4 | Kliknij akcję `Generate AI` (ikona ⚡) | Otwiera się modal z wyborem języka i stylu. | ☐ |
| 3.5 | Wypełnij i zatwierdź modal `Generate AI` | Pojawia się notyfikacja "Generation queued successfully". W tle zadanie trafia do kolejki. | ☐ |
| 3.6 | Wejdź w edycję zasobu (np. `/admin/movies/{id}/edit`) | Formularz edycji ładuje się poprawnie. | ☐ |
| 3.7 | Sprawdź sekcję `Descriptions` na stronie edycji | Widoczna jest tabela z listą wygenerowanych opisów dla danego zasobu. | ☐ |

---

### 4. Zarządzanie Raportami

| Krok | Akcja | Oczekiwany Rezultat | Status |
| :--- | :---- | :------------------ | :----: |
| 4.1 | Wejdź na listę raportów (`/admin/reports`) | Tabela z listą zgłoszeń ładuje się poprawnie. (Jeśli pusta, dodaj raport przez API lub tinker). | ☐ |
| 4.2 | Użyj filtra `Status` i wybierz `pending` | Tabela pokazuje tylko raporty o statusie "pending". | ☐ |
| 4.3 | Użyj filtra `Entity Type` i wybierz `movie` | Tabela pokazuje tylko raporty dotyczące filmów. | ☐ |
| 4.4 | Kliknij akcję `Verify & Regenerate` | Akcja jest widoczna (jeśli status to `pending`). Po kliknięciu pojawia się potwierdzenie. | ☐ |

---
