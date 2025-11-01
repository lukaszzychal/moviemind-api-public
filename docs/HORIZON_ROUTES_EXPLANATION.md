# 🔗 Co oznacza "Horizon routes są zarejestrowane"?

**Data:** 2025-11-01

---

## 📍 Co to znaczy?

**"Horizon routes są zarejestrowane"** oznacza, że **Laravel automatycznie dodał wszystkie ścieżki HTTP dla Horizon dashboard** do systemu routingu aplikacji.

---

## 🔍 Jak to działa?

### **1. Service Provider rejestruje routes**

**Lokalizacja:** `api/app/Providers/HorizonServiceProvider.php`

```php
<?php

namespace App\Providers;

use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot(); // ← To wywołuje rejestrację routes!
    }
}
```

**Co się dzieje w `parent::boot()`:**
- `HorizonApplicationServiceProvider` (parent class) automatycznie rejestruje wszystkie routes Horizon
- Routes są dostępne pod prefixem `/horizon/*`

---

### **2. Service Provider jest zarejestrowany**

**Lokalizacja:** `api/bootstrap/providers.php`

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\HorizonServiceProvider::class, // ← Horizon zarejestrowany!
];
```

**Kiedy Laravel startuje:**
1. Ładuje wszystkie Service Providers z `providers.php`
2. Wywołuje `boot()` dla każdego
3. `HorizonServiceProvider::boot()` → `parent::boot()` → rejestruje routes

---

### **3. Routes są dostępne**

**Sprawdzenie routes:**

```bash
php artisan route:list | grep horizon
```

**Wynik:**
```
GET|HEAD   horizon/{view?}              horizon.index
GET|HEAD   horizon/api/stats            horizon.stats.index
GET|HEAD   horizon/api/jobs/pending     horizon.pending-jobs.index
GET|HEAD   horizon/api/jobs/completed   horizon.completed-jobs.index
GET|HEAD   horizon/api/jobs/failed      horizon.failed-jobs.index
... i wiele innych
```

**Dostępne endpointy:**
- `GET /horizon/dashboard` - Główny dashboard (UI)
- `GET /horizon/api/stats` - Statystyki (JSON API)
- `GET /horizon/api/jobs/pending` - Pending jobs (JSON API)
- `GET /horizon/api/jobs/failed` - Failed jobs (JSON API)
- `GET /horizon/api/jobs/{id}` - Szczegóły joba (JSON API)
- ... i wiele innych

---

## 🎯 Dlaczego to jest ważne?

### **Bez rejestracji routes:**
```bash
GET /horizon/dashboard
# → 404 Not Found
```

### **Z rejestracją routes:**
```bash
GET /horizon/dashboard
# → 200 OK (Horizon UI)
```

---

## 📊 Flow diagram

```
┌─────────────────────────────────────────────────────────┐
│ Laravel Application Start                               │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│ bootstrap/providers.php                                 │
│                                                         │
│ return [                                                │
│     ...,                                               │
│     App\Providers\HorizonServiceProvider::class, ✅    │
│ ];                                                      │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│ HorizonServiceProvider::boot()                          │
│                                                         │
│ public function boot(): void                           │
│ {                                                       │
│     parent::boot();  ✅ Wywołuje rejestrację routes   │
│ }                                                       │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│ HorizonApplicationServiceProvider::boot()               │
│ (parent class z Laravel\Horizon)                        │
│                                                         │
│ Automatycznie rejestruje:                               │
│ - Route::get('/horizon/{view?}', ...)                   │
│ - Route::get('/horizon/api/stats', ...)                 │
│ - Route::get('/horizon/api/jobs/*', ...)                │
│ - ... i wiele innych                                    │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│ Routes dostępne!                                         │
│                                                         │
│ http://localhost:8000/horizon/dashboard  ✅             │
│ http://localhost:8000/horizon/api/stats  ✅             │
└─────────────────────────────────────────────────────────┘
```

---

## ✅ Jak sprawdzić czy routes są zarejestrowane?

### **Metoda 1: Artisan route:list**

```bash
docker-compose exec php php artisan route:list | grep horizon
```

**Oczekiwany wynik:**
```
GET|HEAD   horizon/{view?}          horizon.index
GET|HEAD   horizon/api/stats        horizon.stats.index
... (więcej routes)
```

---

### **Metoda 2: Test HTTP request**

```bash
curl http://localhost:8000/horizon/dashboard
```

**Oczekiwany wynik:**
- **200 OK** - HTML z Horizon dashboard
- **404 Not Found** - routes nie są zarejestrowane

---

### **Metoda 3: Sprawdzenie Service Provider**

```bash
cat api/bootstrap/providers.php
```

**Oczekiwany wynik:**
```php
return [
    // ...
    App\Providers\HorizonServiceProvider::class, // ✅ Obecny
];
```

---

## 🔧 Co jeśli routes NIE są zarejestrowane?

### **Problem:**
```
GET /horizon/dashboard → 404 Not Found
```

### **Rozwiązanie:**

**1. Sprawdź czy HorizonServiceProvider jest w providers.php:**
```php
// api/bootstrap/providers.php
return [
    // ...
    App\Providers\HorizonServiceProvider::class, // ← Musi być!
];
```

**2. Sprawdź czy Horizon jest zainstalowany:**
```bash
composer show laravel/horizon
```

**3. Wyczyść cache:**
```bash
php artisan config:clear
php artisan route:clear
```

**4. Sprawdź routes:**
```bash
php artisan route:list | grep horizon
```

---

## 📝 Przykłady routes Horizon

| Route | Metoda | Opis |
|-------|--------|------|
| `/horizon` | GET | Przekierowuje do `/horizon/dashboard` |
| `/horizon/dashboard` | GET | Główny dashboard UI |
| `/horizon/api/stats` | GET | Statystyki (JSON) |
| `/horizon/api/jobs/pending` | GET | Pending jobs (JSON) |
| `/horizon/api/jobs/completed` | GET | Completed jobs (JSON) |
| `/horizon/api/jobs/failed` | GET | Failed jobs (JSON) |
| `/horizon/api/jobs/{id}` | GET | Szczegóły joba (JSON) |
| `/horizon/api/batches` | GET | Job batches (JSON) |
| `/horizon/api/workload` | GET | Workload stats (JSON) |

---

## 🎯 Podsumowanie

**"Horizon routes są zarejestrowane"** = Laravel automatycznie dodał wszystkie ścieżki HTTP dla Horizon dashboard do routingu aplikacji, dzięki:

1. ✅ `HorizonServiceProvider` jest zarejestrowany w `bootstrap/providers.php`
2. ✅ `HorizonServiceProvider::boot()` wywołuje `parent::boot()` 
3. ✅ `HorizonApplicationServiceProvider` (parent) rejestruje routes automatycznie
4. ✅ Routes są dostępne pod `/horizon/*`

**Efekt:** Dashboard dostępny pod `http://localhost:8000/horizon/dashboard` ✅

---

**Ostatnia aktualizacja:** 2025-11-01

