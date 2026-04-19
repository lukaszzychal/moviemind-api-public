# MovieMind API - Podsumowanie Projektu

> **Dla:** Rozmów kwalifikacyjnych, rekruterów, prezentacji technicznych  
> **Ostatnia aktualizacja:** 2026-04-19  
> **Status:** Projekt portfolio / Demo

---

## 🎯 Szybki przegląd

**MovieMind API** to usługa RESTful API, która generuje unikalne, oparte na sztucznej inteligencji opisy filmów, seriali oraz aktorów. W przeciwieństwie do tradycyjnych baz danych filmowych, które kopiują treści z IMDb czy TMDb, MovieMind tworzy oryginalną zawartość od zera przy użyciu modeli GPT od OpenAI.

**Główny wyróżnik:** Oryginalna treść generowana przez AI, a nie kopiowane metadane.

---

## 💼 Szczegóły projektu

### Czym on jest?
Gotowa do produkcji usługa API, która:
- Generuje unikalne opisy filmów/seriali przy użyciu AI.
- Obsługuje wiele języków (pl-PL, en-US itd.).
- Zapewnia dobór stylu (nowoczesny, krytyczny, humorystyczny).
- Zarządza subskrypcjami z rate limitingiem (ograniczaniem liczby żądań).
- Integruje się z zewnętrznymi API (TMDB, TVmaze) w celu weryfikacji danych.
- Obsługuje przetwarzanie asynchroniczne via Laravel Horizon.

### Stack technologiczny
- **Backend:** Laravel 12 (PHP 8.2+)
- **Baza danych:** PostgreSQL (produkcja i testy)
- **Cache/Kolejka:** Redis + Laravel Horizon
- **AI:** OpenAI API (gpt-4o-mini)
- **Testowanie:** PHPUnit (Feature + Unit), Playwright (E2E)
- **Jakość kodu:** Laravel Pint, PHPStan, GitLeaks
- **Deployment:** Docker Compose (lokalnie), Nginx + PHP-FPM

### Architektura
- **Wzorzec:** Modular Monolith z Feature-Based Scaling.
- **Wzorce projektowe:** Repository, Service Layer, Action, Event-Driven, Response Formatter.
- **Styl architektury:** Architektura warstwowa (HTTP → Logika biznesowa → Dostęp do danych).
- **Przetwarzanie asynchroniczne:** Event-Driven (Events → Listeners → Jobs → Kolejka).

---

## 🚀 Główne funkcjonalności

### Rdzeń funkcjonalny
1. **Generowanie treści AI**
   - Tworzenie unikalnych opisów dla filmów, seriali i aktorów.
   - Wsparcie dla wielu języków i stylów kontekstowych.
   - Przetwarzanie asynchroniczne przez system kolejek.

2. **Zarządzanie encjami**
   - Filmy, Osoby, Seriale, Programy TV.
   - Identyfikacja oparta na slugach (`the-matrix-1999`).
   - Disambiguation (rozstrzyganie niejednoznaczności) dla tytułów o tej samej nazwie.
   - Operacje masowe (do 100 encji).

3. **Wyszukiwanie i odkrywanie**
   - Wyszukiwanie pełnotekstowe we wszystkich typach encji.
   - Rekomendacje powiązanych treści.
   - Porównywanie encji.

4. **System subskrypcji**
   - Trzy poziomy: Free, Pro, Enterprise.
   - Autoryzacja oparta na kluczach API.
   - Rate limiting zależny od planu.
   - Analityka użycia.

5. **Integracje zewnętrzne**
   - TMDB (weryfikacja filmów/osób).
   - TVmaze (weryfikacja seriali/programów).
   - OpenAI (generowanie treści AI).

### Zaawansowane funkcje
- **Wielojęzyczność:** Wsparcie dla pl-PL, en-US i innych.
- **Style kontekstowe:** Nowoczesne, krytyczne lub zabawne opisy.
- **Inteligentne cachowanie:** Oparte na Redis z zarządzaniem czasem wygasania (TTL).
- **Przetwarzanie asynchroniczne:** Laravel Horizon dla zadań w tle.
- **HATEOAS:** Linki hipermedialne w odpowiedziach API.
- **Wersjonowanie:** Wersjonowanie opisów z możliwością archiwizacji.
- **Raportowanie:** System zgłaszania problemów z treścią przez użytkowników.
- **Panel Admina:** Interfejs administracyjny oparty na Filament.

---

## 🏗️ Atuty architektury

### Dlaczego te wzorce?

**Cienkie Kontrolery (Thin Controllers):**
- Obsługują tylko kwestie HTTP (maks. 20-30 linii).
- Logika biznesowa delegowana do Serwisów/Akcji.
- **Korzyść:** Łatwość testowania, utrzymania i ponownego użycia.

**Warstwa Serwisów (Service Layer):**
- Hermetyzacja logiki biznesowej.
- Koordynacja między repozytoriami a zewnętrznymi API.
- **Korzyść:** Scentralizowana logika, wysoka testowalność.

**Wzorzec Repozytorium (Repository Pattern):**
- Abstrakcja warstwy dostępu do danych.
- Hermetyzacja zapytań do bazy.
- **Korzyść:** Testowalność (mockowanie), elastyczność w zmianie magazynu danych.

**Wzorzec Akcji (Action Pattern):**
- Pojedyncze operacje biznesowe.
- Złożone przepływy pracy zamknięte w jednej klasie.
- **Korzyść:** Zasada pojedynczej odpowiedzialności, łatwość komponowania.

**Architektura Sterowana Zdarzeniami (Event-Driven):**
- Flow: Events → Listeners → Jobs.
- Rozdzielenie (decoupling) komponentów.
- **Korzyść:** Skalowalność, łatwa rozszerzalność, luźne powiązania.

---

## 📊 Metryki techniczne

### Jakość kodu
- **Pokrycie testami:** 859 przechodzących testów (Unit + Feature + E2E).
- **Styl kodu:** Laravel Pint (PSR-12).
- **Analiza statyczna:** PHPStan (poziom 5).
- **Bezpieczeństwo:** GitLeaks (wykrywanie sekretów).

### Wydajność
- **Cachowanie:** Oparte na Redis (TMDB: 6 miesięcy, TVmaze: bezterminowo).
- **Kolejki:** Laravel Horizon (przetwarzanie asynchroniczne).
- **Baza danych:** PostgreSQL z optymalnym indeksowaniem.
- **Rate Limiting:** Zależny od planu (10-1000 żądań/minutę).

### Skalowalność
- **Skalowanie horyzontalne:** Stateles API (możliwość skalowania w poziomie).
- **Workerzy kolejek:** Wiele instancji workerów Horizon.
- **Baza danych:** Wsparcie dla replik do odczytu (read replicas).
- **Cachowanie:** Wsparcie dla klastrów Redis.

---

## 🔧 Workflow deweloperski

### Lokalny rozwój
- **Docker Compose:** Obowiązkowy (PostgreSQL, Redis, Nginx, PHP-FPM).
- **TDD:** Test-Driven Development (pisanie testów przed kodem).
- **Pre-commit Hooks:** Pint, PHPStan, GitLeaks, testy.

### Strategia testowania
- **Testy jednostkowe (Unit):** Serwisy, Akcje, Helpery (szybkie, izolowane).
- **Testy funkcjonalne (Feature):** Endpointy API, integracje (kompleksowe).
- **Testy E2E:** Playwright (krytyczne ścieżki użytkownika).

### Narzędzia jakości
- **Laravel Pint:** Formatowanie kodu (PSR-12).
- **PHPStan:** Analiza statyczna (poziom 5).
- **GitLeaks:** Wykrywanie sekretów.
- **Composer Audit:** Audyt podatności bibliotek.

---

## 📈 Model biznesowy

### Plany subskrypcyjne
- **Free:** 100 żądań/miesiąc, dostęp tylko do odczytu.
- **Pro:** 10,000 żądań/miesiąc, generowanie AI, tagi kontekstowe.
- **Enterprise:** Nielimitowane żądania, webhooks, analityka, priorytetowe wsparcie.

### Portfolio / Demo
- Obecnie używa lokalnych kluczy API (demonstracja portfolio).
- Pełna funkcjonalność dla celów demo.
- Kod gotowy na produkcję (wymaga licencji komercyjnych dla TMDB).

---

## 🔐 Funkcje bezpieczeństwa

- **Autoryzacja kluczem API:** Hashowane klucze, bezpieczne przechowywanie.
- **Rate Limiting:** Limity oparte na planach z użyciem Redis.
- **Walidacja wejścia:** Form Requests, restrykcyjna walidacja.
- **Wykrywanie sekretów:** GitLeaks w hookach pre-commit.
- **Zapobieganie SQL Injection:** Eloquent ORM (zapytania parametryzowane).
- **Zapobieganie XSS:** Escaping na wyjściu, odpowiedzi JSON.

---

## 📚 Dokumentacja

### Kompleksowa dokumentacja
- **Business:** Funkcje, wymagania, plany subskrypcyjne.
- **Technical:** Architektura, specyfikacja API, deployment, integracje.
- **QA:** Strategia testów, manualne plany testowe, testy automatyczne.
- **Legal:** Wymagania licencyjne TMDB/TVmaze.

### Dokumentacja kodu
- **PHPDoc:** Wszystkie klasy i metody udokumentowane.
- **README:** Instrukcja konfiguracji, przegląd architektury.
- **API Docs:** Specyfikacja OpenAPI.

---

## 🎯 Cele projektu

### Cele główne
1. **Projekt Portfolio:** Demonstracja umiejętności Full-Stack.
2. **Kod gotowy na produkcję:** Czysty, przetestowany, udokumentowany.
3. **Najlepsze praktyki:** SOLID, DRY, TDD, Clean Architecture.
4. **Realne funkcjonalności:** Subskrypcje, rate limiting, przetwarzanie asynchroniczne.

### Przyszłe ulepszenia
- Integracja Stripe/PayPal dla płatności.
- GraphQL API.
- Wsparcie dla WebSockets (aktualizacje w czasie rzeczywistym).
- Zaawansowany dashboard analityczny.
- Wsparcie dla multi-tenancy.

---

## 📞 Kontakt i linki

- **Repozytorium:** [GitHub](https://github.com/lukaszzychal/moviemind-api-public)
- **Dokumentacja:** Katalog `docs/`
- **Specyfikacja API:** `docs/openapi.yaml`
- **Status:** Projekt Portfolio/Demo (Kod gotowy na produkcję)

---

**Ten projekt demonstruje:**
- Rozwój Full-stack (Backend API).
- Nowoczesny rozwój PHP/Laravel.
- Zasady Clean Architecture.
- Strategie testowania (Piramida testów, TDD).
- Praktyki DevOps (Docker, CI/CD).
- Projektowanie i dokumentowanie API.
- Najlepsze praktyki bezpieczeństwa.

**Gotowy do wdrożenia produkcyjnego** (z licencjami komercyjnymi dla TMDB).
