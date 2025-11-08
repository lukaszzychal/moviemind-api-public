# AI Service - Quick Start Guide

## 🚀 Szybki Start

### 1. Dodaj do `.env`:

```env
# AI Service Configuration
# Options: 'mock' (default) or 'real'
AI_SERVICE=mock
```

### 2. Użycie:

**Development (Mock):**
```env
AI_SERVICE=mock
```
- Używa `MockAiService`
- Symuluje AI (sleep, mock data)
- Dla lokalnego development i testów

**Production (Real):**
```env
AI_SERVICE=real
```
- Używa `RealAiService`
- Używa Events + Jobs architecture
- Można zintegrować z prawdziwym AI API

### 3. Sprawdź która implementacja jest używana:

```bash
php artisan tinker
>>> app(App\Services\AiServiceInterface::class)
App\Services\MockAiService # lub App\Services\RealAiService
```

---

## 📊 Architektura

**MockAiService:**
- Controller → MockAiService → Bus::dispatch(closure)

**RealAiService:**
- Controller → RealAiService → Event → Listener → Job

---

## 🔧 Zmiana Implementacji

Po zmianie `.env`:
```bash
php artisan config:clear
# LUB restart aplikacji
```

---

Zobacz `docs/AI_SERVICE_CONFIGURATION.md` dla szczegółów.

