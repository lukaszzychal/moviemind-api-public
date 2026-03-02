# MovieMind API - Podsumowanie Projektu

> **Dla:** Rozmowy Rekrutacyjne, Rekruterzy, Prezentacje Techniczne  
> **Ostatnia aktualizacja:** 2026-01-22  
> **Status:** Projekt Portfolio/Demo

---

## 🎯 Szybki Przegląd

**MovieMind API** to RESTful API, które generuje unikalne, oparte na AI opisy filmów, seriali TV i aktorów. W przeciwieństwie do tradycyjnych baz danych filmowych, które kopiują treści z IMDb lub TMDb, MovieMind tworzy oryginalne treści od zera używając modeli GPT OpenAI.

**Kluczowa różnica:** Oryginalne treści generowane przez AI, nie skopiowane metadane.

---

## 💼 Szczegóły Projektu

### Czym jest?
Gotowa do produkcji usługa API, która:
- Generuje unikalne opisy filmów/seriali używając AI
- Obsługuje wiele języków (pl-PL, en-US, itp.)
- Zapewnia kontekstowe style (nowoczesny, krytyczny, humorystyczny)
- Zarządza subskrypcjami z limitami zapytań
- Integruje się z zewnętrznymi API (TMDB, TVmaze) do weryfikacji
- Obsługuje asynchroniczne przetwarzanie przez Laravel Horizon

### Stack Technologiczny
- **Backend:** Laravel 12 (PHP 8.2+)
- **Baza danych:** PostgreSQL (produkcja i testy)
- **Cache/Kolejka:** Redis + Laravel Horizon
- **AI:** OpenAI API (gpt-4o-mini)
- **Testowanie:** PHPUnit (Feature + Unit), Playwright (E2E)
- **Jakość kodu:** Laravel Pint, PHPStan, GitLeaks
- **Deployment:** Docker Compose (lokalnie), Nginx + PHP-FPM

### Architektura
- **Wzorzec:** Modularny Monolit z Feature-Based Scaling
- **Wzorce projektowe:** Repository, Service Layer, Action, Event-Driven, Response Formatter
- **Styl architektury:** Architektura warstwowa (HTTP → Logika biznesowa → Dostęp do danych)
- **Przetwarzanie asynchroniczne:** Event-Driven (Events → Listeners → Jobs → Queue)

---

## 🚀 Kluczowe Funkcje

### Funkcjonalność Podstawowa
1. **Generowanie Treści AI**
   - Generuje unikalne opisy dla filmów, seriali TV, aktorów
   - Obsługuje wiele języków i kontekstowych stylów
   - Asynchroniczne przetwarzanie przez system kolejek

2. **Zarządzanie Encjami**
   - Filmy, Osoby, Seriale TV, Programy TV
   - Identyfikacja oparta na slugach (`the-matrix-1999`)
   - Rozróżnianie dla niejednoznacznych tytułów
   - Operacje masowe (do 100 encji)

3. **Wyszukiwanie i Odkrywanie**
   - Pełnotekstowe wyszukiwanie we wszystkich typach encji
   - Rekomendacje powiązanych treści
   - Porównywanie między encjami

4. **System Subskrypcji**
   - Trzy poziomy: Free, Pro, Enterprise
   - Uwierzytelnianie oparte na kluczach API
   - Rate limiting oparty na planach
   - Analityka użycia

5. **Integracje Zewnętrzne**
   - TMDB (weryfikacja filmów/osób)
   - TVmaze (weryfikacja seriali/programów TV)
   - OpenAI (generowanie treści AI)

### Zaawansowane Funkcje
- **Obsługa Wielojęzyczna:** pl-PL, en-US i więcej
- **Kontekstowe Style:** Opisy nowoczesne, krytyczne, humorystyczne
- **Inteligentne Cache'owanie:** Cache oparty na Redis z zarządzaniem TTL
- **Przetwarzanie Asynchroniczne:** Laravel Horizon dla zadań w tle
- **HATEOAS:** Linki hipermedialne w odpowiedziach API
- **Wersjonowanie:** Wersjonowanie opisów z archiwizacją
- **Raportowanie:** System raportowania użytkowników dla problemów z treścią
- **Panel Administracyjny:** Interfejs admin oparty na Filament

---

## 🏗️ Najważniejsze Elementy Architektury

### Dlaczego Te Wzorce?

**Thin Controllers:**
- Kontrolery obsługują tylko sprawy HTTP (max 20-30 linii)
- Logika biznesowa delegowana do Services/Actions
- **Korzyść:** Łatwe do testowania, łatwe w utrzymaniu, reużywalne

**Service Layer:**
- Serwisy enkapsulują logikę biznesową
- Koordynują między repozytoriami a zewnętrznymi API
- **Korzyść:** Scentralizowana logika, testowalna, reużywalna

**Repository Pattern:**
- Abstrakcja warstwy dostępu do danych
- Enkapsuluje zapytania do bazy danych
- **Korzyść:** Testowalna (mock repozytoriów), elastyczna (zamiana implementacji)

**Action Pattern:**
- Pojedyncze operacje biznesowe
- Złożone przepływy enkapsulowane
- **Korzyść:** Pojedyncza odpowiedzialność, komponowalna, testowalna

**Event-Driven Architecture:**
- Events → Listeners → Jobs
- Rozdziela komponenty
- **Korzyść:** Skalowalna, rozszerzalna, luźne sprzężenie

---

## 📊 Metryki Techniczne

### Jakość Kodu
- **Pokrycie Testami:** 859 przechodzących testów (Unit + Feature + E2E)
- **Styl Kodu:** Laravel Pint (PSR-12)
- **Analiza Statyczna:** PHPStan (poziom 5)
- **Bezpieczeństwo:** GitLeaks (wykrywanie sekretów)

### Wydajność
- **Cache'owanie:** Oparte na Redis (TMDB: 6 miesięcy, TVmaze: bezterminowo)
- **Kolejka:** Laravel Horizon (przetwarzanie asynchroniczne)
- **Baza danych:** PostgreSQL z właściwym indeksowaniem
- **Rate Limiting:** Oparty na planach (10-1000 zapytań/minutę)

### Skalowalność
- **Skalowanie Poziome:** Bezstanowe API (można skalować poziomo)
- **Workery Kolejki:** Wiele workerów Horizon
- **Baza danych:** Wsparcie dla replik odczytu
- **Cache:** Wsparcie dla klastra Redis

---

## 🔧 Workflow Rozwoju

### Rozwój Lokalny
- **Docker Compose:** Obowiązkowe (PostgreSQL, Redis, Nginx, PHP-FPM)
- **TDD:** Test-Driven Development (najpierw testy)
- **Pre-commit Hooks:** Pint, PHPStan, GitLeaks, testy

### Strategia Testowania
- **Testy Unit:** Serwisy, Actions, Helpers (szybkie, izolowane)
- **Testy Feature:** Endpointy API, integracje (kompleksowe)
- **Testy E2E:** Playwright (krytyczne przepływy użytkownika)

### Narzędzia Jakości Kodu
- **Laravel Pint:** Formatowanie kodu (PSR-12)
- **PHPStan:** Analiza statyczna (poziom 5)
- **GitLeaks:** Wykrywanie sekretów
- **Composer Audit:** Luki bezpieczeństwa

---

## 📈 Model Biznesowy

### Plany Subskrypcji
- **Free:** 100 zapytań/miesiąc, dostęp tylko do odczytu
- **Pro:** 10,000 zapytań/miesiąc, generowanie AI, tagi kontekstowe
- **Enterprise:** Nieograniczone zapytania, webhooks, analityka, priorytetowe wsparcie

### Portfolio/Demo
- Obecnie używa lokalnych kluczy API (demonstracja portfolio)
- Pełna funkcjonalność do celów demo
- Kod gotowy do produkcji (wymaga licencji komercyjnych dla TMDB)

---

## 🔐 Funkcje Bezpieczeństwa

- **Uwierzytelnianie Kluczem API:** Zahashowane klucze, bezpieczne przechowywanie
- **Rate Limiting:** Limity oparte na planach z Redis
- **Walidacja Wejścia:** Form Requests, ścisła walidacja
- **Wykrywanie Sekretów:** GitLeaks w pre-commit hooks
- **Zapobieganie SQL Injection:** Eloquent ORM (zapytania parametryzowane)
- **Zapobieganie XSS:** Escapowanie wyjścia, odpowiedzi JSON

---

## 📚 Dokumentacja

### Kompleksowa Dokumentacja
- **Biznesowa:** Funkcje, Wymagania, Plany Subskrypcji
- **Techniczna:** Architektura, Specyfikacja API, Deployment, Integracje
- **QA:** Strategia Testów, Plany Testów Manualnych, Testy Automatyczne
- **Prawna:** Wymagania licencyjne TMDB/TVmaze

### Dokumentacja Kodu
- **PHPDoc:** Wszystkie klasy i metody udokumentowane
- **README:** Instrukcje konfiguracji, przegląd architektury
- **API Docs:** Specyfikacja OpenAPI

---

## 🎯 Cele Projektu

### Cele Główne
1. **Projekt Portfolio:** Demonstracja umiejętności full-stack development
2. **Kod Gotowy do Produkcji:** Czysty, przetestowany, udokumentowany
3. **Najlepsze Praktyki:** SOLID, DRY, TDD, Clean Architecture
4. **Funkcje Real-World:** Subskrypcje, rate limiting, przetwarzanie asynchroniczne

### Przyszłe Ulepszenia
- Integracja Stripe/PayPal dla rozliczeń
- API GraphQL
- Wsparcie WebSocket dla aktualizacji w czasie rzeczywistym
- Zaawansowany dashboard analityczny
- Wsparcie multi-tenant

---

## 📞 Kontakt i Linki

- **Repozytorium:** [GitHub](https://github.com/lukaszzychal/moviemind-api-public)
- **Dokumentacja:** Zobacz katalog `docs/`
- **Specyfikacja API:** `docs/openapi.yaml`
- **Status:** Projekt Portfolio/Demo (kod gotowy do produkcji)

---

**Ten projekt demonstruje:**
- Full-stack development (Backend API)
- Nowoczesny rozwój PHP/Laravel
- Zasady Clean Architecture
- Strategie testowania (TDD, Piramida Testów)
- Praktyki DevOps (Docker, CI/CD)
- Projektowanie i dokumentacja API
- Najlepsze praktyki bezpieczeństwa

**Gotowy do wdrożenia produkcyjnego** (z licencjami komercyjnymi dla TMDB).
