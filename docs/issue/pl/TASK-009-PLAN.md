# 📋 TASK-009: Admin UI - Plan Realizacji

**Status:** 🔄 IN_PROGRESS  
**Branch:** `feature/TASK-009-admin-ui`  
**Priorytet:** 🟢 Niski (Roadmap)  
**Szacowany czas:** 15-20h  
**Data rozpoczęcia:** 2025-01-08

---

## 🎯 Cel zadania

Implementacja panelu administracyjnego dla zarządzania treścią MovieMind API:
- Zarządzanie filmami (Movies)
- Zarządzanie osobami (People)
- Zarządzanie feature flags
- Monitoring i analytics (już zrealizowane w TASK-010)

---

## 🔍 Analiza rozwiązań

### Porównanie frameworków (2025)

| Framework | Licencja | Koszt | Zalety | Wady | Rekomendacja |
|-----------|----------|-------|--------|------|--------------|
| **Filament** | MIT | Darmowy | ✅ Open-source<br>✅ Livewire + Tailwind<br>✅ Nowoczesny UI<br>✅ Świetna dokumentacja<br>✅ Aktywna społeczność<br>✅ Łatwa rozbudowa | ⚠️ Wymaga Livewire | ⭐⭐⭐⭐⭐ **WYBÓR** |
| **Nova** | Proprietary | $199/dev | ✅ Oficjalny Laravel<br>✅ Elegancki UI<br>✅ Wsparcie Taylor'a | ❌ Płatny<br>❌ Trudna rozbudowa<br>❌ Vendor lock-in | ⭐⭐⭐ |
| **Backpack** | Dual (MIT/Commercial) | $0-$99 | ✅ Dojrzały<br>✅ Dużo funkcji | ⚠️ Starszy stack<br>⚠️ Bootstrap | ⭐⭐⭐ |
| **Breeze** | MIT | Darmowy | ✅ Minimalistyczny<br>✅ Starter kit | ❌ Wymaga dużo custom kodu<br>❌ Brak CRUD | ⭐⭐ |

### 🏆 Decyzja: **Laravel Filament v3**

**Uzasadnienie:**
1. **Open-source** - brak kosztów licencji
2. **Nowoczesny stack** - Livewire 3 + Tailwind CSS 3 + Alpine.js
3. **Najlepsza w 2025** - według community i benchmarków
4. **Łatwa integracja** - z istniejącym projektem Laravel
5. **Świetna dokumentacja** - filamentphp.com
6. **Aktywny rozwój** - regularne aktualizacje
7. **Ecosystem** - plugins, themes, community packages

---

## 📐 Architektura rozwiązania

### Struktura katalogów

```
api/
├── app/
│   ├── Filament/
│   │   ├── Resources/
│   │   │   ├── MovieResource.php
│   │   │   ├── PersonResource.php
│   │   │   └── FeatureFlagResource.php
│   │   ├── Pages/
│   │   │   └── Dashboard.php
│   │   └── Widgets/
│   │       ├── StatsOverview.php
│   │       └── RecentActivity.php
│   └── Policies/
│       ├── MoviePolicy.php
│       └── PersonPolicy.php
├── config/
│   └── filament.php
└── resources/
    └── views/
        └── filament/
            └── pages/
                └── dashboard.blade.php
```

### Routing

```
/admin                    → Dashboard (Filament)
/admin/movies             → Movies CRUD
/admin/people             → People CRUD
/admin/feature-flags      → Feature Flags management
/horizon                  → Queue monitoring (już istnieje)
```

### Bezpieczeństwo

- **Basic Auth** - już zaimplementowane dla `/admin/*` (TASK-050)
- **Laravel Policies** - kontrola dostępu na poziomie modeli
- **Filament Shield** (opcjonalnie) - zarządzanie rolami i uprawnieniami

---

## 📝 Plan implementacji

### Faza 1: Instalacja i konfiguracja (2-3h)

#### 1.1 Instalacja Filament
```bash
cd api
composer require filament/filament:"^3.2"
php artisan filament:install --panels
```

#### 1.2 Konfiguracja
- [ ] Skonfigurować `config/filament.php`
- [ ] Ustawić prefix `/admin`
- [ ] Skonfigurować middleware (Basic Auth już istnieje)
- [ ] Dodać logo i branding MovieMind

#### 1.3 Utworzenie użytkownika admin
```bash
php artisan make:filament-user
```

**Deliverables:**
- ✅ Filament zainstalowany
- ✅ Panel admin dostępny pod `/admin`
- ✅ Basic Auth działa
- ✅ Użytkownik admin utworzony

---

### Faza 2: Movie Resource (4-5h)

#### 2.1 Utworzenie Resource
```bash
php artisan make:filament-resource Movie --generate
```

#### 2.2 Konfiguracja pól formularza
- [ ] `title` - TextInput (required)
- [ ] `slug` - TextInput (readonly, auto-generated)
- [ ] `release_year` - TextInput (numeric, 4 digits)
- [ ] `director` - TextInput
- [ ] `genres` - TagsInput (array)
- [ ] `tmdb_id` - TextInput (nullable)
- [ ] `default_description_id` - Select (relation)

#### 2.3 Konfiguracja tabeli
- [ ] Kolumny: title, release_year, director, genres
- [ ] Filtry: rok, gatunek
- [ ] Wyszukiwanie: title, director
- [ ] Sortowanie: title, release_year
- [ ] Bulk actions: delete

#### 2.4 Relacje
- [ ] Descriptions (HasMany)
- [ ] People (BelongsToMany) - cast
- [ ] Reports (HasMany)

#### 2.5 Walidacja
- [ ] Wykorzystać istniejące FormRequest
- [ ] Walidacja slug uniqueness
- [ ] Walidacja TMDB ID format

**Deliverables:**
- ✅ CRUD dla Movies
- ✅ Walidacja działa
- ✅ Relacje wyświetlane
- ✅ Testy jednostkowe

---

### Faza 3: Person Resource (4-5h)

#### 3.1 Utworzenie Resource
```bash
php artisan make:filament-resource Person --generate
```

#### 3.2 Konfiguracja pól formularza
- [ ] `name` - TextInput (required)
- [ ] `slug` - TextInput (readonly, auto-generated)
- [ ] `birth_date` - DatePicker
- [ ] `birthplace` - TextInput
- [ ] `tmdb_id` - TextInput (nullable)
- [ ] `default_bio_id` - Select (relation)

#### 3.3 Konfiguracja tabeli
- [ ] Kolumny: name, birth_date, birthplace
- [ ] Filtry: rok urodzenia
- [ ] Wyszukiwanie: name, birthplace
- [ ] Sortowanie: name, birth_date

#### 3.4 Relacje
- [ ] Bios (HasMany)
- [ ] Movies (BelongsToMany) - filmografia
- [ ] Reports (HasMany)

**Deliverables:**
- ✅ CRUD dla People
- ✅ Walidacja działa
- ✅ Relacje wyświetlane
- ✅ Testy jednostkowe

---

### Faza 4: Feature Flags Management (2-3h)

#### 4.1 Analiza istniejącego systemu
- [ ] Sprawdzić `app/Features/`
- [ ] Zidentyfikować wszystkie feature flags
- [ ] Określić strukturę danych

#### 4.2 Implementacja zarządzania
**Opcja A: Custom Page (prostsze)**
- [ ] Utworzyć `Filament\Pages\FeatureFlags`
- [ ] Lista wszystkich flag z statusem (enabled/disabled)
- [ ] Toggle switches dla włączania/wyłączania
- [ ] Grupowanie: Product vs Developer flags

**Opcja B: Resource (bardziej zaawansowane)**
- [ ] Utworzyć model `FeatureFlag` (jeśli nie istnieje)
- [ ] Migracja dla tabeli `feature_flags`
- [ ] Resource z CRUD

**Rekomendacja:** Opcja A (Custom Page) - prostsze, wystarczające

#### 4.3 Integracja z Laravel Pennant
- [ ] Sprawdzić czy projekt używa Pennant
- [ ] Jeśli tak - integracja z Pennant API
- [ ] Jeśli nie - custom implementation

**Deliverables:**
- ✅ Zarządzanie feature flags
- ✅ UI dla włączania/wyłączania
- ✅ Dokumentacja użycia

---

### Faza 5: Dashboard i Widgets (2-3h)

#### 5.1 Dashboard Overview
- [ ] Stats Overview Widget:
  - Total Movies
  - Total People
  - Pending Generations (queue)
  - Failed Jobs (last 24h)

#### 5.2 Recent Activity Widget
- [ ] Ostatnio dodane filmy (5)
- [ ] Ostatnio dodane osoby (5)
- [ ] Ostatnie generacje AI (5)

#### 5.3 Quick Actions
- [ ] "Add Movie" button
- [ ] "Add Person" button
- [ ] "View Queue" link (→ Horizon)
- [ ] "View Analytics" link (→ TASK-010 dashboard)

**Deliverables:**
- ✅ Dashboard z widgetami
- ✅ Quick actions
- ✅ Linki do Horizon i Analytics

---

### Faza 6: Testy i dokumentacja (2-3h)

#### 6.1 Testy Feature
```php
tests/Feature/Filament/
├── MovieResourceTest.php
├── PersonResourceTest.php
└── FeatureFlagsPageTest.php
```

**Test cases:**
- [ ] Dostęp do panelu admin (Basic Auth)
- [ ] CRUD operations dla Movies
- [ ] CRUD operations dla People
- [ ] Feature flags toggle
- [ ] Walidacja formularzy
- [ ] Policies (jeśli zaimplementowane)

#### 6.2 Dokumentacja
- [ ] `docs/admin/README.md` - ogólny opis
- [ ] `docs/admin/MOVIES.md` - zarządzanie filmami
- [ ] `docs/admin/PEOPLE.md` - zarządzanie osobami
- [ ] `docs/admin/FEATURE_FLAGS.md` - zarządzanie flagami
- [ ] Screenshots w dokumentacji

#### 6.3 Aktualizacja TASKS.md
- [ ] Zmienić status TASK-009 na ✅ COMPLETED
- [ ] Dodać czas realizacji
- [ ] Dodać summary of changes

**Deliverables:**
- ✅ Testy pokrywają >80% kodu
- ✅ Dokumentacja kompletna
- ✅ TASKS.md zaktualizowane

---

## 🔧 Konfiguracja techniczna

### Wymagania

```json
{
  "php": "^8.2",
  "laravel/framework": "^11.0",
  "filament/filament": "^3.2",
  "livewire/livewire": "^3.0"
}
```

### Environment Variables

```env
# Admin Panel
FILAMENT_ADMIN_PATH=/admin
FILAMENT_BRAND_NAME="MovieMind Admin"
FILAMENT_BRAND_LOGO=/images/logo.svg

# Basic Auth (już istnieje)
ADMIN_USERNAME=admin
ADMIN_PASSWORD=secret
```

### Middleware Stack

```php
// config/filament.php
'middleware' => [
    'web',
    'auth.basic',  // już zaimplementowane w TASK-050
],
```

---

## 🎨 UI/UX Guidelines

### Design System
- **Colors:** Tailwind default palette
- **Typography:** Inter font family
- **Icons:** Heroicons (default Filament)
- **Dark mode:** Enabled (Filament default)

### Branding
- Logo: MovieMind (🎬)
- Primary color: Blue (#3B82F6)
- Accent color: Purple (#8B5CF6)

---

## 🚀 Deployment

### Development
```bash
php artisan serve
# Admin panel: http://localhost:8000/admin
```

### Production
- Admin panel będzie dostępny pod `/admin`
- Basic Auth już skonfigurowane (TASK-050)
- HTTPS required (enforced by middleware)

---

## ✅ Definition of Done

- [ ] Filament zainstalowany i skonfigurowany
- [ ] Movie Resource z pełnym CRUD
- [ ] Person Resource z pełnym CRUD
- [ ] Feature Flags management
- [ ] Dashboard z widgetami
- [ ] Testy Feature (>80% coverage)
- [ ] Dokumentacja kompletna
- [ ] Basic Auth działa
- [ ] Code review passed
- [ ] Merged to `main`
- [ ] TASKS.md zaktualizowane

---

## 📊 Estymacja czasu

| Faza | Zadanie | Czas |
|------|---------|------|
| 1 | Instalacja i konfiguracja | 2-3h |
| 2 | Movie Resource | 4-5h |
| 3 | Person Resource | 4-5h |
| 4 | Feature Flags | 2-3h |
| 5 | Dashboard i Widgets | 2-3h |
| 6 | Testy i dokumentacja | 2-3h |
| **TOTAL** | | **16-22h** |

**Szacowany czas:** 15-20h (zgodnie z TASK-009)  
**Realny czas:** 16-22h (z buforem)

---

## 🔗 Zależności

- ✅ TASK-050 - Basic Auth dla admin endpoints (COMPLETED)
- ✅ TASK-010 - Analytics Dashboard (COMPLETED) - integracja przez linki
- ✅ Laravel Horizon - już zainstalowany

---

## 📚 Referencje

- [Filament Documentation](https://filamentphp.com/docs)
- [Filament Resources](https://filamentphp.com/docs/3.x/panels/resources)
- [Filament Widgets](https://filamentphp.com/docs/3.x/panels/dashboard)
- [Laravel Policies](https://laravel.com/docs/11.x/authorization#creating-policies)

---

## 🎯 Success Metrics

- ✅ Admin może zarządzać filmami bez edycji bazy danych
- ✅ Admin może zarządzać osobami bez edycji bazy danych
- ✅ Admin może włączać/wyłączać feature flags
- ✅ Dashboard pokazuje kluczowe metryki
- ✅ UI jest intuicyjny i responsywny
- ✅ Testy pokrywają krytyczne ścieżki

---

**Autor:** AI Agent  
**Data utworzenia:** 2025-01-08  
**Ostatnia aktualizacja:** 2025-01-08
