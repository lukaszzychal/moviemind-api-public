# Admin Panel - Dokumentacja QA

## 🧪 Plan Testów

### Zakres Testów
- ✅ **Testy Automatyczne:** 933 testy (501 Feature + 432 Unit)
- 📋 **Testy Manualne:** Scenariusze poniżej
- 🔄 **Testy Regresji:** Po każdym update
- 🚀 **Testy Akceptacyjne:** Przed release

---

## 📋 Test Cases - Movies CRUD

### TC-001: Dodanie Nowego Filmu
**Priorytet:** HIGH  
**Typ:** Functional  
**Środowisko:** Dev, Staging

**Warunki Wstępne:**
- Użytkownik zalogowany do `/admin`
- Brak filmu o tytule "Test Movie"

**Kroki:**
1. Przejdź do "Movies" → "New Movie"
2. Wypełnij formularz:
   - Title: "Test Movie"
   - Release Year: 2024
   - Director: "Test Director"
   - Genres: "Action, Drama"
   - TMDb ID: 12345
3. Kliknij "Save"

**Oczekiwany Rezultat:**
- ✅ Film zapisany w bazie
- ✅ Slug auto-generowany: "test-movie"
- ✅ Redirect do listy filmów
- ✅ Notification: "Movie created successfully"
- ✅ Film widoczny w tabeli

**Kryteria Akceptacji:**
- Wszystkie pola zapisane poprawnie
- Slug unikalny
- Walidacja działa

---

### TC-002: Edycja Istniejącego Filmu
**Priorytet:** HIGH  
**Typ:** Functional

**Warunki Wstępne:**
- Film "Test Movie" istnieje w bazie

**Kroki:**
1. Przejdź do "Movies"
2. Znajdź "Test Movie"
3. Kliknij "Edit"
4. Zmień "Release Year" na 2025
5. Kliknij "Save"

**Oczekiwany Rezultat:**
- ✅ Rok zmieniony na 2025
- ✅ Notification: "Movie updated successfully"
- ✅ Inne pola niezmienione

---

### TC-003: Usunięcie Filmu
**Priorytet:** HIGH  
**Typ:** Functional

**Warunki Wstępne:**
- Film "Test Movie" istnieje w bazie

**Kroki:**
1. Przejdź do "Movies"
2. Znajdź "Test Movie"
3. Kliknij "Delete"
4. Potwierdź usunięcie

**Oczekiwany Rezultat:**
- ✅ Film usunięty z bazy
- ✅ Notification: "Movie deleted successfully"
- ✅ Film nie widoczny w tabeli

---

### TC-004: Walidacja - Tytuł Wymagany
**Priorytet:** MEDIUM  
**Typ:** Validation

**Kroki:**
1. Przejdź do "Movies" → "New Movie"
2. Pozostaw pole "Title" puste
3. Wypełnij inne pola
4. Kliknij "Save"

**Oczekiwany Rezultat:**
- ❌ Formularz nie zapisany
- ✅ Błąd walidacji: "The title field is required."
- ✅ Focus na polu "Title"

---

### TC-005: Walidacja - Slug Unikalny
**Priorytet:** MEDIUM  
**Typ:** Validation

**Warunki Wstępne:**
- Film o slug "inception" istnieje

**Kroki:**
1. Przejdź do "Movies" → "New Movie"
2. Title: "Inception"
3. Slug auto-generuje się jako "inception"
4. Kliknij "Save"

**Oczekiwany Rezultat:**
- ❌ Formularz nie zapisany
- ✅ Błąd walidacji: "The slug has already been taken."

---

### TC-006: Walidacja - Rok Wydania (Range)
**Priorytet:** LOW  
**Typ:** Validation

**Kroki:**
1. Przejdź do "Movies" → "New Movie"
2. Title: "Future Movie"
3. Release Year: 2200
4. Kliknij "Save"

**Oczekiwany Rezultat:**
- ❌ Formularz nie zapisany
- ✅ Błąd walidacji: "The release year must not be greater than 2100."

---

### TC-007: Auto-generowanie Slug
**Priorytet:** HIGH  
**Typ:** Functional

**Kroki:**
1. Przejdź do "Movies" → "New Movie"
2. Title: "The Matrix Reloaded"
3. Kliknij poza polem "Title" (blur)

**Oczekiwany Rezultat:**
- ✅ Slug auto-generuje się: "the-matrix-reloaded"
- ✅ Slug edytowalny (można zmienić ręcznie)

---

### TC-008: Wyszukiwanie Filmów
**Priorytet:** MEDIUM  
**Typ:** Functional

**Warunki Wstępne:**
- Filmy w bazie: "Inception", "Interstellar", "The Dark Knight"

**Kroki:**
1. Przejdź do "Movies"
2. W polu wyszukiwania wpisz "Inter"

**Oczekiwany Rezultat:**
- ✅ Wyświetlony tylko "Interstellar"
- ✅ Inne filmy ukryte

---

### TC-009: Filtrowanie po Roku Wydania
**Priorytet:** MEDIUM  
**Typ:** Functional

**Warunki Wstępne:**
- Filmy: "Inception" (2010), "Interstellar" (2014), "Tenet" (2020)

**Kroki:**
1. Przejdź do "Movies"
2. Kliknij "Filters"
3. Release Year From: 2010
4. Release Year To: 2015
5. Zastosuj filtr

**Oczekiwany Rezultat:**
- ✅ Wyświetlone: "Inception", "Interstellar"
- ✅ Ukryte: "Tenet"

---

### TC-010: Sortowanie po Tytule
**Priorytet:** LOW  
**Typ:** Functional

**Warunki Wstępne:**
- Filmy: "Zootopia", "Avatar", "Inception"

**Kroki:**
1. Przejdź do "Movies"
2. Kliknij nagłówek kolumny "Title"

**Oczekiwany Rezultat:**
- ✅ Filmy posortowane alfabetycznie: Avatar, Inception, Zootopia
- ✅ Kliknięcie ponowne: sortowanie malejące (Z-A)

---

## 📋 Test Cases - People CRUD

### TC-011: Dodanie Nowej Osoby
**Priorytet:** HIGH  
**Typ:** Functional

**Kroki:**
1. Przejdź do "People" → "New Person"
2. Wypełnij formularz:
   - Name: "Tom Hanks"
   - Birth Date: 1956-07-09
   - Birthplace: "Concord, California, USA"
   - TMDb ID: 31
3. Kliknij "Save"

**Oczekiwany Rezultat:**
- ✅ Osoba zapisana w bazie
- ✅ Slug auto-generowany: "tom-hanks"
- ✅ Notification: "Person created successfully"

---

### TC-012: Edycja Osoby
**Priorytet:** HIGH  
**Typ:** Functional

**Warunki Wstępne:**
- Osoba "Tom Hanks" istnieje

**Kroki:**
1. Przejdź do "People"
2. Znajdź "Tom Hanks"
3. Kliknij "Edit"
4. Zmień "Birthplace" na "Concord, CA"
5. Kliknij "Save"

**Oczekiwany Rezultat:**
- ✅ Birthplace zmienione
- ✅ Notification: "Person updated successfully"

---

### TC-013: Walidacja - Data Urodzenia (Przyszłość)
**Priorytet:** MEDIUM  
**Typ:** Validation

**Kroki:**
1. Przejdź do "People" → "New Person"
2. Name: "Future Person"
3. Birth Date: 2030-01-01
4. Kliknij "Save"

**Oczekiwany Rezultat:**
- ❌ Formularz nie zapisany
- ✅ Błąd walidacji: "The birth date must not be after today."

---

### TC-014: Filtrowanie po Roku Urodzenia
**Priorytet:** MEDIUM  
**Typ:** Functional

**Warunki Wstępne:**
- Osoby: "Tom Hanks" (1956), "Leonardo DiCaprio" (1974)

**Kroki:**
1. Przejdź do "People"
2. Kliknij "Filters"
3. Birth Year From: 1970
4. Birth Year To: 1980
5. Zastosuj filtr

**Oczekiwany Rezultat:**
- ✅ Wyświetlony: "Leonardo DiCaprio"
- ✅ Ukryty: "Tom Hanks"

---

## 📋 Test Cases - Feature Flags

### TC-015: Przegląd Feature Flags
**Priorytet:** MEDIUM  
**Typ:** Functional

**Kroki:**
1. Przejdź do "Feature Flags"

**Oczekiwany Rezultat:**
- ✅ Wyświetlone wszystkie flagi z `config/pennant.php`
- ✅ Flagi pogrupowane po kategoriach
- ✅ Toggle controls dla flag boolean
- ✅ Disabled toggle dla flag non-boolean

---

### TC-016: Włączenie Feature Flag (In-Memory)
**Priorytet:** LOW  
**Typ:** Functional

**Kroki:**
1. Przejdź do "Feature Flags"
2. Znajdź flagę "ai_content_moderation"
3. Przełącz toggle na ON
4. Kliknij "Save Changes"

**Oczekiwany Rezultat:**
- ✅ Notification: "Feature flags updated"
- ✅ Notification body: "Note: Changes are in-memory only..."
- ⚠️ Zmiany NIE persystowane w `config/pennant.php`

---

### TC-017: Weryfikacja Braku Persystencji
**Priorytet:** MEDIUM  
**Typ:** Functional

**Warunki Wstępne:**
- Flaga "ai_content_moderation" włączona (TC-016)

**Kroki:**
1. Odśwież stronę (F5)

**Oczekiwany Rezultat:**
- ✅ Flaga "ai_content_moderation" z powrotem OFF
- ✅ Zgodne z `config/pennant.php`

---

## 📋 Test Cases - Dashboard

### TC-018: Wyświetlanie Statystyk
**Priorytet:** HIGH  
**Typ:** Functional

**Warunki Wstępne:**
- 10 filmów w bazie
- 5 osób w bazie
- 3 pending jobs
- 1 failed job (ostatnie 24h)

**Kroki:**
1. Przejdź do `/admin` (Dashboard)

**Oczekiwany Rezultat:**
- ✅ Total Movies: 10
- ✅ Total People: 5
- ✅ Pending Jobs: 3
- ✅ Failed Jobs: 1

---

### TC-019: Auto-refresh Statystyk
**Priorytet:** LOW  
**Typ:** Functional

**Warunki Wstępne:**
- Dashboard otwarty

**Kroki:**
1. Pozostaw Dashboard otwarty przez 30 sekund
2. W międzyczasie dodaj nowy film (w innej karcie)

**Oczekiwany Rezultat:**
- ✅ Po 30s: Total Movies zwiększone o 1
- ✅ Polling interval: 30s

---

### TC-020: Link do Horizon
**Priorytet:** LOW  
**Typ:** Functional

**Kroki:**
1. Przejdź do Dashboard
2. Kliknij link "Horizon"

**Oczekiwany Rezultat:**
- ✅ Redirect do `/horizon`
- ✅ Horizon dashboard wyświetlony

---

## 📋 Test Cases - Autentykacja

### TC-021: Logowanie - Poprawne Credentials
**Priorytet:** CRITICAL  
**Typ:** Security

**Kroki:**
1. Przejdź do `/admin`
2. Email: admin@moviemind.local
3. Password: password123
4. Kliknij "Sign in"

**Oczekiwany Rezultat:**
- ✅ Zalogowany
- ✅ Redirect do Dashboard

---

### TC-022: Logowanie - Niepoprawne Credentials
**Priorytet:** CRITICAL  
**Typ:** Security

**Kroki:**
1. Przejdź do `/admin`
2. Email: admin@moviemind.local
3. Password: wrongpassword
4. Kliknij "Sign in"

**Oczekiwany Rezultat:**
- ❌ Nie zalogowany
- ✅ Błąd: "These credentials do not match our records."

---

### TC-023: Basic Auth - Poprawne Credentials
**Priorytet:** CRITICAL  
**Typ:** Security

**Warunki Wstępne:**
- Basic Auth włączony (TASK-050)

**Kroki:**
1. Przejdź do `/admin`
2. Popup Basic Auth
3. Username: admin (z `.env`)
4. Password: secret (z `.env`)

**Oczekiwany Rezultat:**
- ✅ Basic Auth passed
- ✅ Redirect do Filament Login

---

### TC-024: Basic Auth - Niepoprawne Credentials
**Priorytet:** CRITICAL  
**Typ:** Security

**Kroki:**
1. Przejdź do `/admin`
2. Popup Basic Auth
3. Username: wrong
4. Password: wrong

**Oczekiwany Rezultat:**
- ❌ 401 Unauthorized
- ✅ Popup Basic Auth ponownie

---

### TC-025: Wylogowanie
**Priorytet:** HIGH  
**Typ:** Security

**Warunki Wstępne:**
- Użytkownik zalogowany

**Kroki:**
1. Kliknij avatar (prawy górny róg)
2. Kliknij "Sign out"

**Oczekiwany Rezultat:**
- ✅ Wylogowany
- ✅ Redirect do `/admin/login`
- ✅ Session cleared

---

## 📋 Test Cases - UI/UX

### TC-026: Responsywność - Mobile
**Priorytet:** MEDIUM  
**Typ:** UI

**Kroki:**
1. Otwórz `/admin` w Chrome DevTools
2. Ustaw viewport: iPhone 12 (390x844)
3. Przejdź przez wszystkie strony

**Oczekiwany Rezultat:**
- ✅ Sidebar zwijane (hamburger menu)
- ✅ Tabele scrollowalne poziomo
- ✅ Formularze czytelne
- ✅ Buttony klikalne (min 44x44px)

---

### TC-027: Dark Mode
**Priorytet:** LOW  
**Typ:** UI

**Kroki:**
1. Przejdź do `/admin`
2. Kliknij ikonę słońca/księżyca (toggle dark mode)

**Oczekiwany Rezultat:**
- ✅ Przełączenie na dark mode
- ✅ Wszystkie elementy czytelne
- ✅ Kontrast wystarczający (WCAG AA)

---

### TC-028: Toggleable Columns
**Priorytet:** LOW  
**Typ:** UI

**Kroki:**
1. Przejdź do "Movies"
2. Kliknij ikonę kolumn (prawy górny róg tabeli)
3. Odznacz "Slug"

**Oczekiwany Rezultat:**
- ✅ Kolumna "Slug" ukryta
- ✅ Inne kolumny widoczne
- ✅ Preferencje zapisane (session)

---

## 📋 Test Cases - Performance

### TC-029: Czas Ładowania Dashboard
**Priorytet:** MEDIUM  
**Typ:** Performance

**Kroki:**
1. Otwórz Chrome DevTools → Network
2. Przejdź do `/admin`
3. Zmierz czas ładowania

**Oczekiwany Rezultat:**
- ✅ Czas ładowania < 2s
- ✅ DOMContentLoaded < 1s

---

### TC-030: Czas Zapisu Filmu
**Priorytet:** MEDIUM  
**Typ:** Performance

**Kroki:**
1. Przejdź do "Movies" → "New Movie"
2. Wypełnij formularz
3. Kliknij "Save"
4. Zmierz czas do notification

**Oczekiwany Rezultat:**
- ✅ Czas zapisu < 500ms
- ✅ Notification wyświetlone natychmiast

---

## 📋 Test Cases - Regresji

### TC-031: Regresja - Slug Duplication
**Priorytet:** HIGH  
**Typ:** Regression

**Warunki Wstępne:**
- Film "Inception" (slug: "inception") istnieje

**Kroki:**
1. Otwórz 2 karty przeglądarki
2. W obu: "Movies" → "New Movie"
3. W obu: Title: "Inception"
4. W obu: Kliknij "Save" jednocześnie

**Oczekiwany Rezultat:**
- ✅ Jedna karta: Film zapisany
- ❌ Druga karta: Błąd walidacji "Slug already taken"
- ✅ Brak duplikatu w bazie

---

### TC-032: Regresja - XSS w Title
**Priorytet:** CRITICAL  
**Typ:** Security

**Kroki:**
1. Przejdź do "Movies" → "New Movie"
2. Title: `<script>alert('XSS')</script>`
3. Kliknij "Save"
4. Przejdź do listy filmów

**Oczekiwany Rezultat:**
- ✅ Tytuł wyświetlony jako tekst (escaped)
- ❌ Alert NIE wyświetlony
- ✅ Brak wykonania JS

---

## 🔄 Test Automation

### Testy Automatyczne
```bash
# Feature Tests
php artisan test --testsuite=Feature
# 501 passed

# Unit Tests
php artisan test --testsuite=Unit
# 432 passed

# Total
php artisan test
# 933 tests, 3827 assertions
```

**Status:** ✅ All passing

### Testy E2E (TODO)
```bash
# Laravel Dusk
composer require laravel/dusk --dev
php artisan dusk:install
php artisan dusk
```

**Rekomendacja:** Dodać testy Dusk dla krytycznych scenariuszy (TC-001, TC-011, TC-021)

---

## 📊 Test Metrics

### Coverage
| Moduł | Unit Tests | Feature Tests | E2E Tests | Coverage |
|-------|------------|---------------|-----------|----------|
| Movies CRUD | ✅ | ✅ | ⏳ | 85% |
| People CRUD | ✅ | ✅ | ⏳ | 85% |
| Feature Flags | ❌ | ❌ | ⏳ | 0% |
| Dashboard | ❌ | ❌ | ⏳ | 0% |
| Auth | ✅ | ✅ | ⏳ | 90% |

**Uwaga:** Filament ma wbudowane testy, więc dedykowane testy nie są wymagane dla podstawowych funkcji CRUD.

### Test Execution Time
| Suite | Tests | Time |
|-------|-------|------|
| Feature | 501 | 46.54s |
| Unit | 432 | 19.07s |
| **Total** | **933** | **65.61s** |

---

## 🐛 Bug Report Template

### Bug ID: BUG-XXX
**Tytuł:** [Krótki opis problemu]

**Priorytet:** Critical / High / Medium / Low  
**Severity:** Blocker / Major / Minor / Trivial  
**Status:** Open / In Progress / Resolved / Closed

**Środowisko:**
- OS: macOS / Windows / Linux
- Browser: Chrome 120 / Firefox 121 / Safari 17
- Laravel: 11.x
- Filament: 3.2

**Kroki Reprodukcji:**
1. Krok 1
2. Krok 2
3. Krok 3

**Oczekiwany Rezultat:**
[Co powinno się stać]

**Rzeczywisty Rezultat:**
[Co się stało]

**Screenshots:**
[Załącz screenshoty]

**Logi:**
```
[Załącz logi z storage/logs/laravel.log]
```

**Workaround:**
[Jeśli istnieje obejście problemu]

---

## ✅ Checklist - Pre-Release

### Funkcjonalność
- [ ] Wszystkie test cases (TC-001 do TC-032) przeszły
- [ ] Testy automatyczne (933) przeszły
- [ ] Brak critical/high bugs

### Performance
- [ ] Dashboard ładuje się < 2s
- [ ] Zapis filmu < 500ms
- [ ] Brak memory leaks

### Security
- [ ] Basic Auth działa
- [ ] Filament Login działa
- [ ] XSS protection działa
- [ ] CSRF protection działa
- [ ] Brak secrets w logach

### UI/UX
- [ ] Responsywność (mobile, tablet, desktop)
- [ ] Dark mode działa
- [ ] Wszystkie ikony wyświetlają się
- [ ] Notifications działają

### Dokumentacja
- [ ] README.md zaktualizowany
- [ ] BUSINESS.md zaktualizowany
- [ ] TECHNICAL.md zaktualizowany
- [ ] QA.md zaktualizowany

### Deployment
- [ ] Assets zbudowane (`php artisan filament:assets`)
- [ ] Cache wyczyszczony
- [ ] Permissions ustawione (775)
- [ ] Environment variables skonfigurowane

---

## 📞 Kontakt QA Team

**QA Lead:** TBD  
**Email:** qa@moviemind.local  
**Slack:** #moviemind-qa  
**Jira:** [TASK-009](https://jira.moviemind.local/browse/TASK-009)

---

**Utworzono:** 2025-01-08  
**Wersja:** 1.0  
**Następna Aktualizacja:** Po każdym release  
**Status:** ✅ READY FOR TESTING
