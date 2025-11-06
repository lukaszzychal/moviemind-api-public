# 📖 Różnica między `env()` a `config()` w Laravel

## 🎯 Krótka Odpowiedź

**`env()`** - odczytuje bezpośrednio z `.env` (tylko w plikach `config/`)  
**`config()`** - odczytuje z cache'owanego pliku config (można wszędzie)

---

## 🔍 Szczegółowe Wyjaśnienie

### **1. `env()` - Environment Variables**

**Gdzie używać:** Tylko w plikach `config/*.php`

```php
// ✅ DOBRZE - w config/services.php
'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
],
```

**Gdzie NIE używać:** W kodzie aplikacji (poza config)

```php
// ❌ ŹLE - w app/Services/OpenAiClient.php
$this->apiKey = env('OPENAI_API_KEY'); // ❌ Może zwrócić null w production!
```

**Dlaczego?**
- W production Laravel cache'uje config: `php artisan config:cache`
- Po cache'owaniu `env()` zwraca `null` (nie czyta `.env`)
- PHPStan zgłasza warning: "Called 'env' outside of the config directory"

---

### **2. `config()` - Cached Configuration**

**Gdzie używać:** Wszędzie w kodzie aplikacji

```php
// ✅ DOBRZE - w app/Services/OpenAiClient.php
$this->apiKey = config('services.openai.api_key');
```

**Jak działa:**
1. Odczytuje z pliku `config/services.php`
2. Jeśli config jest cache'owany → odczytuje z cache
3. Jeśli config nie jest cache'owany → odczytuje z `config/services.php`, który używa `env()`

---

## 📊 Flow Odczytu Wartości

### **Bez Cache Config (development):**
```
.env
  ↓ env('OPENAI_API_KEY')
config/services.php
  ↓ config('services.openai.api_key')
app/Services/OpenAiClient.php
```

### **Z Cache Config (production):**
```
php artisan config:cache  → bootstrap/cache/config.php
                                    ↓
                         config('services.openai.api_key')
                                    ↓
                      app/Services/OpenAiClient.php
```

**Ważne:** Po `config:cache`, plik `.env` nie jest już czytany przez `env()`!

---

## ✅ Przykłady

### **Przed Naprawą (❌ Błąd PHPStan):**

```php
// api/app/Services/OpenAiClient.php
public function __construct()
{
    // ❌ PHPStan warning: env() outside config directory
    $this->apiKey = config('services.openai.api_key') ?? env('OPENAI_API_KEY') ?? '';
    
    // Problem: env() może zwrócić null w production po config:cache
}
```

### **Po Naprawie (✅ Poprawne):**

```php
// api/app/Services/OpenAiClient.php
public function __construct()
{
    // ✅ Tylko config() - działa zawsze (z cache i bez)
    $this->apiKey = (string) (config('services.openai.api_key') ?? '');
}
```

---

## 📋 Zasady Użycia

### **✅ DOBRZE:**

**W `config/*.php`:**
```php
// config/services.php
'openai' => [
    'api_key' => env('OPENAI_API_KEY'), // ✅ OK - to jest plik config
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
],
```

**W kodzie aplikacji:**
```php
// app/Services/OpenAiClient.php
$this->apiKey = config('services.openai.api_key'); // ✅ OK - używa config()
```

---

### **❌ ŹLE:**

```php
// app/Services/OpenAiClient.php
$this->apiKey = env('OPENAI_API_KEY'); // ❌ Błąd w production!
```

**Dlaczego?**
- W production: `php artisan config:cache`
- Po cache: `env()` zwraca `null`
- Aplikacja nie działa!

---

## 🔄 Nasz Przypadek

### **Przed:**
```php
// OpenAiClient.php (BŁĄD)
$this->apiKey = config('services.openai.api_key') ?? env('OPENAI_API_KEY') ?? '';
//                                              ^^^^^^^^^^^^^^^^^^^^^^^^
//                                              To jest problematyczne!
```

### **Po:**
```php
// OpenAiClient.php (POPRAWNIE)
$this->apiKey = (string) (config('services.openai.api_key') ?? '');
//                        ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
//                        Tylko config() - bezpieczne
```

**`config/services.php` już ma:**
```php
'openai' => [
    'api_key' => env('OPENAI_API_KEY'), // ✅ Tu env() jest OK
],
```

---

## 💡 Podsumowanie

| Miejsce | Użyj | Dlaczego |
|---------|------|----------|
| `config/*.php` | `env()` | ✅ To jest jedyne miejsce gdzie env() działa |
| Kod aplikacji | `config()` | ✅ Działa z cache i bez cache |
| Kod aplikacji | `env()` | ❌ Nie działa w production po cache |

---

## 🎯 Wzorzec

```php
// ✅ WZORZEC PRAWIDŁOWY:

// 1. W config/services.php
'openai' => [
    'api_key' => env('OPENAI_API_KEY'), // ✅ env() tylko tu
],

// 2. W kodzie aplikacji
$this->apiKey = config('services.openai.api_key'); // ✅ config() wszędzie indziej
```

---

**Ostatnia aktualizacja:** 2025-11-01

