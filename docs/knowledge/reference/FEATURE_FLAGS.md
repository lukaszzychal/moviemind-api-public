# Feature Flags – konfiguracja i zarządzanie

> **Data utworzenia:** 2025-11-10  
> **Kontekst:** Centralizacja konfiguracji flag Pennant oraz utwardzenie endpointów administracyjnych.  
> **Kategoria:** reference

## 🎯 Cel

Udokumentowanie nowego podejścia do zarządzania feature flagami w MovieMind API: konfiguracja w `config/pennant.php`, informacje meta o flagach oraz ograniczenia endpointów toggle.

## 📋 Zawartość

### Struktura `config/pennant.php`

- `flags` – słownik flag z atrybutami:
  - `class` – przypisana klasa `App\Features\*`.
  - `description` – opis prezentowany w API/GUI.
  - `category` – logiczna kategoria (core_ai, moderation, i18n, …).
  - `default` – wartość domyślna, z której korzysta `BaseFeature`.
  - `togglable` – czy można zmienić stan poprzez API admina.
- `features` – lista klas przekazywana do Pennanta (wynik mapowania z `flags`).
- `default`/`stores` – domyślne ustawienia storage Pennant (database/array).

### Rozszerzone API admina

- `GET /api/v1/admin/flags` zwraca dodatkowe pola `category`, `default`, `togglable`. Dane pochodzą z serwisu `App\Services\FeatureFlag\FeatureFlagManager`.
- `POST /api/v1/admin/flags/{name}` (walidacja w `App\Http\Requests\Admin\SetFlagRequest`):
  - `404` dla nieznanych flag,
  - `403` gdy `togglable === false`.
- `GET /api/v1/admin/flags/usage` filtruje wyniki tylko do flag zdefiniowanych w konfiguracji dzięki `App\Services\FeatureFlag\FeatureFlagUsageScanner`.

### Integracja z klasami Feature

- Nowa klasa bazowa `App\Features\BaseFeature` odczytuje wartość domyślną z konfiguracji (SnakeCase nazwy klasy → klucz w `flags`).
- Wszystkie klasy w `app/Features/*` rozszerzają `BaseFeature`, dzięki czemu zmiana domyślnego stanu wymaga jedynie aktualizacji konfiguracji.

### Tablica flag (skrót)

| Flaga                     | Kategoria     | Domyślna | Można togglować |
|---------------------------|---------------|----------|-----------------|
| ai_description_generation | core_ai       | true     | tak             |
| ai_bio_generation         | core_ai       | true     | tak             |
| human_moderation_required | moderation    | false    | tak             |
| public_jobs_polling       | public_api    | true     | tak             |
| (pozostałe)               | różne         | różnie   | nie             |

Pełna lista i opisy znajdują się w `config/pennant.php`.

## 🔗 Powiązane Dokumenty

- [TASK_018_FEATURE_FLAGS.md](../../tasks/TASK_018_FEATURE_FLAGS.md)
- [docs/openapi.yaml](../../openapi.yaml) – zaktualizowane schematy odpowiedzi

## 📌 Notatki

- Dodając nową flagę, uzupełnij `config/pennant.php` (opis, kategoria, togglable) oraz rozważ aktualizację dokumentacji API/Postman.
- Jeżeli flaga ma być modyfikowalna z panelu, ustaw `togglable: true` i dodaj testy pokrywające scenariusz.

---

**Ostatnia aktualizacja:** 2025-11-10

