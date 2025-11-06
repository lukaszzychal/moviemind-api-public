# 🔍 Horizon Dashboard vs Worker - Dlaczego dashboard działa a worker nie?

**Data:** 2025-11-01

---

## ❓ Problem

**Kontener Horizon:**
```
STATUS: Exited (1) About an hour ago
```

**Dashboard:**
```
http://localhost:8000/horizon/dashboard → ✅ Działa (200 OK)
```

**Dlaczego dashboard działa, a kontener nie?**

---

## 🎯 Wyjaśnienie

### **To są 2 RÓŻNE rzeczy!**

#### **1. Horizon Dashboard (HTTP Routes)** ✅ Działa

**Co to jest:**
- Zwykłe HTTP routes w Laravel
- Obsługiwane przez **nginx + php-fpm** (kontener `php`)
- Zwraca HTML/CSS/JS dla UI
- Czyta dane z Redis (ale nie przetwarza jobów)

**Lokalizacja:**
- Routes: automatycznie zarejestrowane przez `HorizonServiceProvider`
- Obsługa: kontener `php` (php-fpm)

**Status:**
```
✅ Działa - bo to tylko routes HTTP
```

---

#### **2. Horizon Worker (Queue Processor)** ❌ Nie działa

**Co to jest:**
- **Osobny proces** który przetwarza joby z Redis
- Musi działać **ciągle** (daemon)
- Nasłuchuje Redis na nowe joby
- Wymaga rozszerzenia **`pcntl`** w PHP

**Lokalizacja:**
- Kontener: `moviemind-horizon`
- Command: `php artisan horizon`
- Wymaga: `pcntl` extension

**Status:**
```
❌ Nie działa - błąd: Call to undefined function pcntl_async_signals()
```

---

## 🔄 Jak to działa razem?

```
┌─────────────────────────────────────────────────────────┐
│ 1. Horizon Dashboard (HTTP Routes)                       │
│                                                         │
│ User → GET /horizon/dashboard                           │
│   ↓                                                     │
│ Nginx → PHP-FPM (kontener php)                         │
│   ↓                                                     │
│ Laravel Routes → HorizonController                      │
│   ↓                                                     │
│ Redis → Czyta dane (statystyki, joby)                  │
│   ↓                                                     │
│ Response → HTML/CSS/JS (dashboard UI)                  │
│                                                         │
│ ✅ Działa bez Horizon Worker!                          │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ 2. Horizon Worker (Queue Processor)                     │
│                                                         │
│ Redis → Nowe joby w kolejce                            │
│   ↓                                                     │
│ Horizon Worker (kontener horizon)                       │
│   ↓                                                     │
│ Przetwarza joby                                         │
│   ↓                                                     │
│ Aktualizuje statystyki w Redis                          │
│   ↓                                                     │
│ Dashboard pokazuje zaktualizowane dane                  │
│                                                         │
│ ❌ Nie działa - brak pcntl                             │
└─────────────────────────────────────────────────────────┘
```

---

## 📊 Porównanie

| Aspekt | Dashboard | Worker |
|--------|-----------|--------|
| **Co to jest** | HTTP Routes (UI) | Queue Processor |
| **Kontener** | `php` (php-fpm) | `horizon` (cli) |
| **Proces** | Obsługuje HTTP requests | Daemon (ciągle działa) |
| **Wymaga pcntl** | ❌ Nie | ✅ Tak |
| **Co robi** | Wyświetla UI, czyta Redis | Przetwarza joby, zapisuje do Redis |
| **Status** | ✅ Działa | ❌ Nie działa |

---

## 🔍 Dlaczego kontener się zatrzymuje?

### **Błąd:**
```
Call to undefined function pcntl_async_signals()
```

### **Przyczyna:**
- Kontener używa **starego obrazu** (bez `pcntl` extension)
- Dockerfile został zaktualizowany, ale obraz nie został przebudowany

### **Rozwiązanie:**

**1. Przebuduj kontener:**
```bash
docker-compose build horizon
```

**2. Uruchom:**
```bash
docker-compose up -d horizon
```

**3. Sprawdź logi:**
```bash
docker-compose logs -f horizon
```

**Oczekiwany output po naprawie:**
```
Horizon started successfully.
```

---

## 🎯 Podsumowanie

### **Dashboard działa bo:**
1. ✅ To tylko HTTP routes
2. ✅ Obsługiwane przez `php` kontener (php-fpm)
3. ✅ Nie wymaga `pcntl`
4. ✅ Czyta dane z Redis (ale nie przetwarza)

### **Worker nie działa bo:**
1. ❌ Wymaga osobnego kontenera (`horizon`)
2. ❌ Wymaga rozszerzenia `pcntl` w PHP
3. ❌ Kontener używa starego obrazu (bez `pcntl`)

### **Efekt:**
- Dashboard: **✅ Działa** (możesz zobaczyć UI)
- Worker: **❌ Nie działa** (joby nie są przetwarzane)
- Dashboard będzie **pusty** (brak danych z workera)

---

## ✅ Co zrobić?

**Opcja 1: Przebuduj kontener (Rekomendowane)**
```bash
docker-compose build horizon
docker-compose up -d horizon
docker-compose logs -f horizon
```

**Opcja 2: Sprawdź czy pcntl jest zainstalowane**
```bash
docker-compose exec php php -m | grep pcntl
```

**Opcja 3: Użyj queue:work jako alternatywa**
```bash
# Jeśli Horizon nie działa, możesz użyć:
docker-compose exec -d php php artisan queue:work redis --tries=3
```

---

**Ostatnia aktualizacja:** 2025-11-01

