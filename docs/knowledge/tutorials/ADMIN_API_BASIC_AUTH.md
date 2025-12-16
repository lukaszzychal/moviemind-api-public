# 🔐 Admin API Basic Authentication - Setup i Konfiguracja

**Data:** 2025-12-16  
**Zadanie:** TASK-050  
**Status:** ✅ Implementacja zakończona

---

## 📋 **Co to jest Admin API Basic Auth?**

**Admin API Basic Auth** to mechanizm zabezpieczenia endpointów administracyjnych (`/api/v1/admin/*`) za pomocą HTTP Basic Authentication. Zapewnia:

- ✅ **Bezpieczeństwo** - endpointy admin są chronione przed nieautoryzowanym dostępem
- ✅ **Elastyczność** - możliwość bypassu w środowiskach development/staging
- ✅ **Wymuszenie w produkcji** - zawsze wymagana autoryzacja w produkcji
- ✅ **Logowanie** - logowanie prób dostępu (udanych i nieudanych)

**Wymagania:**
- Laravel 11+
- Middleware `AdminBasicAuth`
- Zmienne środowiskowe: `ADMIN_ALLOWED_EMAILS`, `ADMIN_BASIC_AUTH_PASSWORD`

---

## 🔧 **Instalacja i Konfiguracja**

### **1. Middleware**

Middleware `AdminBasicAuth` jest już zaimplementowany w:
- `app/Http/Middleware/AdminBasicAuth.php`

### **2. Rejestracja Middleware**

Middleware jest zarejestrowany w `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'horizon.basic' => \App\Http\Middleware\HorizonBasicAuth::class,
        'admin.basic' => \App\Http\Middleware\AdminBasicAuth::class,
    ]);
})
```

### **3. Zastosowanie w Route'ach**

Middleware jest zastosowany do wszystkich route'ów admin w `routes/api.php`:

```php
Route::prefix('v1/admin')->middleware('admin.basic')->group(function () {
    Route::prefix('flags')->group(function () {
        Route::get('/', [FlagController::class, 'index']);
        Route::post('{name}', [FlagController::class, 'setFlag']);
        Route::get('usage', [FlagController::class, 'usage']);
    });
    Route::get('debug/config', [HealthController::class, 'debugConfig']);
});
```

---

## ⚙️ **Konfiguracja Environment Variables**

### **Local Development**

**`.env` (local):**
```env
ADMIN_AUTH_BYPASS_ENVS=local,staging
ADMIN_ALLOWED_EMAILS=
ADMIN_BASIC_AUTH_PASSWORD=
```

**Uwaga:** W środowisku lokalnym autoryzacja jest bypassowana, więc zmienne mogą być puste.

### **Staging**

**`.env` (staging):**
```env
ADMIN_AUTH_BYPASS_ENVS=local,staging
ADMIN_ALLOWED_EMAILS=
ADMIN_BASIC_AUTH_PASSWORD=
```

**Uwaga:** W staging autoryzacja jest bypassowana dla wygody testowania.

### **Production**

**`.env` (production):**
```env
# IMPORTANT: ADMIN_AUTH_BYPASS_ENVS must be empty in production!
ADMIN_AUTH_BYPASS_ENVS=
ADMIN_ALLOWED_EMAILS=admin@example.com,ops@example.com
ADMIN_BASIC_AUTH_PASSWORD=super-secure-password-here-min-32-chars
```

**⚠️ Wymagania w produkcji:**
- `ADMIN_AUTH_BYPASS_ENVS` **MUSI** być puste
- `ADMIN_ALLOWED_EMAILS` **MUSI** zawierać przynajmniej jeden email
- `ADMIN_BASIC_AUTH_PASSWORD` **MUSI** być ustawione (min. 32 znaki zalecane)

---

## 🔐 **Jak to działa?**

### **1. Bypass w Local/Staging**

Jeśli środowisko jest w `ADMIN_AUTH_BYPASS_ENVS`, autoryzacja jest pomijana:

```php
if (in_array($currentEnv, $bypassEnvironments, true)) {
    return $next($request);  // Bypass - dostęp bez autoryzacji
}
```

### **2. Wymuszenie w Produkcji**

W produkcji autoryzacja jest **zawsze wymagana**, nawet jeśli przypadkowo dodano `production` do `ADMIN_AUTH_BYPASS_ENVS`:

```php
if ($currentEnv === 'production') {
    $this->enforceProductionAuth();  // Wymusza konfigurację
}
```

### **3. Weryfikacja Credentials**

1. **Email** - musi być w `ADMIN_ALLOWED_EMAILS` (case-insensitive)
2. **Password** - musi być zgodne z `ADMIN_BASIC_AUTH_PASSWORD` (używa `hash_equals`)

### **4. Logowanie**

Middleware loguje:
- ✅ **Udany dostęp** - email, IP, path
- ⚠️ **Nieudany dostęp** - email, IP, path (z powodu nieautoryzowanego emaila lub błędnego hasła)

---

## 🧪 **Testy**

Testy autoryzacji znajdują się w:
- `tests/Feature/AdminBasicAuthTest.php`

**Pokrycie testów:**
- ✅ Bypass w local/staging
- ✅ Wymaganie autoryzacji w produkcji
- ✅ Dostęp z poprawnymi credentials
- ✅ Odrzucenie z niepoprawnym emailem
- ✅ Odrzucenie z niepoprawnym hasłem
- ✅ Case-insensitive porównanie emaili
- ✅ Wiele autoryzowanych emaili
- ✅ Odrzucenie gdy brak konfiguracji
- ✅ Testy rzeczywistych endpointów admin

**Uruchomienie testów:**
```bash
php artisan test --filter="AdminBasicAuth"
```

---

## 📝 **Użycie**

### **Przykład z curl:**

```bash
# Bez autoryzacji (401 Unauthorized w produkcji)
curl -X GET https://api.moviemind.com/api/v1/admin/flags

# Z autoryzacją (200 OK)
# Użyj opcji -u z emailem i hasłem z ADMIN_ALLOWED_EMAILS i ADMIN_BASIC_AUTH_PASSWORD
curl -X GET \
  -u "email_from_allowed_list:password_from_env" \
  https://api.moviemind.com/api/v1/admin/flags
```

### **Przykład z Postman:**

1. Wybierz metodę HTTP (GET, POST, etc.)
2. Wprowadź URL: `https://api.moviemind.com/api/v1/admin/flags`
3. Przejdź do zakładki **Authorization**
4. Wybierz typ: **Basic Auth**
5. Wprowadź:
   - **Username:** `admin@example.com` (z `ADMIN_ALLOWED_EMAILS`)
   - **Password:** `super-secure-password` (z `ADMIN_BASIC_AUTH_PASSWORD`)

### **Przykład z JavaScript (fetch):**

```javascript
const username = 'admin@example.com';
const password = 'super-secure-password';
const credentials = btoa(`${username}:${password}`);

fetch('https://api.moviemind.com/api/v1/admin/flags', {
  headers: {
    'Authorization': `Basic ${credentials}`
  }
})
  .then(response => response.json())
  .then(data => console.log(data));
```

---

## 🔍 **Monitoring i Logi**

### **Sprawdzanie logów:**

```bash
# Wszystkie próby dostępu do Admin API
grep "Admin API" storage/logs/laravel.log

# Tylko udane dostępy
grep "Admin API access granted" storage/logs/laravel.log

# Tylko nieudane próby
grep "Admin API access denied" storage/logs/laravel.log
```

### **Przykładowe logi:**

**Udany dostęp:**
```
[2025-12-16 10:30:45] local.INFO: Admin API access granted {"email":"admin@example.com","ip":"192.168.1.100","path":"api/v1/admin/flags"}
```

**Nieudany dostęp (nieautoryzowany email):**
```
[2025-12-16 10:31:12] local.WARNING: Admin API access denied - unauthorized email {"email":"hacker@example.com","ip":"192.168.1.200","path":"api/v1/admin/flags"}
```

**Nieudany dostęp (błędne hasło):**
```
[2025-12-16 10:31:45] local.WARNING: Admin API access denied - invalid password {"email":"admin@example.com","ip":"192.168.1.100","path":"api/v1/admin/flags"}
```

**Błąd konfiguracji w produkcji:**
```
[2025-12-16 10:32:00] local.ERROR: Admin API security misconfiguration: ADMIN_ALLOWED_EMAILS is required in production
```

---

## ⚠️ **Bezpieczeństwo**

### **Best Practices:**

1. **Silne hasło:**
   - Minimum 32 znaki
   - Użyj generatora haseł
   - Nie używaj tego samego hasła co do innych systemów

2. **Ograniczenie emaili:**
   - Tylko zaufane adresy email
   - Regularne przeglądy listy autoryzowanych emaili
   - Usuwanie nieużywanych kont

3. **Monitoring:**
   - Regularne sprawdzanie logów
   - Alerty na podejrzane próby dostępu
   - Monitoring failed authentication attempts

4. **Rotacja haseł:**
   - Regularna zmiana hasła (np. co 90 dni)
   - Natychmiastowa zmiana w przypadku podejrzenia kompromitacji

5. **Produkcja:**
   - **NIGDY** nie ustawiaj `ADMIN_AUTH_BYPASS_ENVS` w produkcji
   - **Zawsze** ustaw `ADMIN_ALLOWED_EMAILS` i `ADMIN_BASIC_AUTH_PASSWORD`
   - Middleware wymusza autoryzację w produkcji nawet jeśli bypass jest skonfigurowany

---

## 🔄 **Porównanie z Horizon Basic Auth**

| Aspekt | Horizon Basic Auth | Admin API Basic Auth |
|--------|-------------------|---------------------|
| **Middleware** | `HorizonBasicAuth` | `AdminBasicAuth` |
| **Config** | `config/horizon.php` | Environment variables |
| **Bypass ENV** | `HORIZON_AUTH_BYPASS_ENVS` | `ADMIN_AUTH_BYPASS_ENVS` |
| **Allowed Emails** | `HORIZON_ALLOWED_EMAILS` | `ADMIN_ALLOWED_EMAILS` |
| **Password** | `HORIZON_BASIC_AUTH_PASSWORD` | `ADMIN_BASIC_AUTH_PASSWORD` |
| **Realm** | "Horizon Dashboard" | "Admin API" |
| **Logowanie** | Brak | ✅ Loguje próby dostępu |

---

## 🐛 **Troubleshooting**

### **Problem: 401 Unauthorized w local/staging**

**Przyczyna:** `ADMIN_AUTH_BYPASS_ENVS` nie zawiera środowiska.

**Rozwiązanie:**
```env
ADMIN_AUTH_BYPASS_ENVS=local,staging
```

### **Problem: 401 Unauthorized w produkcji mimo poprawnego hasła**

**Przyczyna:** Email nie jest w `ADMIN_ALLOWED_EMAILS` lub hasło nie jest zgodne.

**Rozwiązanie:**
1. Sprawdź czy email jest w `ADMIN_ALLOWED_EMAILS` (case-insensitive)
2. Sprawdź czy hasło jest dokładnie takie samo (bez spacji na początku/końcu)
3. Sprawdź logi dla szczegółów

### **Problem: Błąd w logach "security misconfiguration"**

**Przyczyna:** W produkcji brakuje `ADMIN_ALLOWED_EMAILS` lub `ADMIN_BASIC_AUTH_PASSWORD`.

**Rozwiązanie:**
```env
ADMIN_ALLOWED_EMAILS=admin@example.com,ops@example.com
ADMIN_BASIC_AUTH_PASSWORD=super-secure-password-here
```

---

## 📚 **Powiązane Dokumenty**

- [TASK-050 dokumentacja](../issue/pl/TASKS.md)
- [Horizon Basic Auth Setup](./HORIZON_SETUP.md)
- [Security Documentation](../../knowledge/technical/APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md)

---

**Ostatnia aktualizacja:** 2025-12-16

