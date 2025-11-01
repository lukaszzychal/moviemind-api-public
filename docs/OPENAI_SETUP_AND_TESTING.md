# 🤖 OpenAI API - Setup i Testowanie

**Data:** 2025-11-01  
**Status:** ✅ Gotowe do testów

---

## 📋 Spis Treści

1. [Jak uzyskać API Key](#jak-uzyskać-api-key)
2. [Wybór modelu](#wybór-modelu)
3. [Konfiguracja w projekcie](#konfiguracja-w-projekcie)
4. [Testowanie](#testowanie)
5. [Troubleshooting](#troubleshooting)

---

## 🔑 Jak uzyskać API Key

### **Krok 1: Rejestracja/Logowanie**

1. Przejdź na: **https://platform.openai.com/**
2. Zaloguj się lub utwórz konto
3. Zweryfikuj email (jeśli wymagane)

---

### **Krok 2: 🆓 Sprawdź darmowe opcje PRZED dodaniem płatności**

**DARMOWE OPCJE (Rekomendowane najpierw!):**

#### **Opcja A: Sprawdź darmowe kredyty OpenAI**

1. Przejdź do: **Settings → Billing → Usage**
2. Sprawdź czy masz dostępne **Credits** lub **Free Tier**
3. 🎁 **OpenAI często daje $5-10 darmowych kredytów** nowym kontom!
4. Jeśli masz kredyty → możesz testować **BEZ PŁATNOŚCI**!

#### **Opcja B: Użyj Mock AI (Całkowicie darmowe)**

Ustaw w `api/.env`:
```env
AI_SERVICE=mock  # Nie wymaga API key!
```

**Zalety Mock AI:**
- ✅ **Całkowicie darmowe**
- ✅ Nie wymaga API key
- ✅ Szybkie (symuluje odpowiedź w 3 sekundy)
- ✅ Idealne do testowania logiki aplikacji

**Kiedy użyć real API:**
- Gdy chcesz przetestować rzeczywistą integrację
- Gdy masz darmowe kredyty OpenAI
- Gdy jesteś gotowy na production (koszty są bardzo niskie)

---

### **Krok 3: Dodanie środków (Tylko jeśli potrzebujesz real API)**

⚠️ **WAŻNE:** To jest opcjonalne jeśli nie masz darmowych kredytów!

1. Przejdź do: **Settings → Billing**
2. Kliknij **"Add payment method"**
3. Dodaj kartę kredytową lub PayPal
4. Ustaw limit (np. $5-10 na start)

**Dlaczego?** OpenAI pobiera opłaty za użycie API (pay-as-you-go), ale koszty są bardzo niskie (~$0.09/100 filmów).

---

### **Krok 3: Generowanie API Key**

1. Przejdź do: **https://platform.openai.com/api-keys**
2. Kliknij **"Create new secret key"**
3. Wpisz nazwę (np. "MovieMind API Development")
4. Kliknij **"Create secret key"**
5. **ZAPISZ KLUCZ NATYCHMIAST** - nie zobaczysz go ponownie!

**Format klucza:**
```
sk-proj-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

⚠️ **Bezpieczeństwo:** Nie udostępniaj klucza publicznie!

---

## 🎯 Wybór modelu

### **Rekomendowany model: `gpt-4o-mini`** ✅

**Dlaczego?**
- ✅ **Tani** - najlepszy stosunek jakość/cena
- ✅ **Szybki** - szybkie odpowiedzi
- ✅ **Wystarczający** - dobra jakość dla generowania opisów filmów
- ✅ **Długi kontekst** - do 128K tokenów

**Cena:**
- Input: **$0.40 / 1M tokenów**
- Output: **$1.60 / 1M tokenów**

---

### **Inne dostępne modele:**

| Model | Cena (input) | Cena (output) | Użycie |
|-------|--------------|----------------|--------|
| `gpt-4o-mini` | $0.40/M | $1.60/M | ✅ **Rekomendowane** |
| `gpt-4o` | $2.50/M | $10.00/M | Premium jakość |
| `gpt-4-turbo` | $10.00/M | $30.00/M | Najlepsza jakość |
| `gpt-3.5-turbo` | $0.50/M | $1.50/M | Starszy model |

**Dla MovieMind API:** `gpt-4o-mini` jest idealny - szybki i tani dla generowania opisów.

---

## ⚙️ Konfiguracja w Projekcie

### **Krok 1: Skonfiguruj `.env`**

**Lokalne środowisko (Docker):**

```bash
# Skopiuj plik .env.example jeśli nie masz .env
cp env/local.env.example api/.env

# Edytuj api/.env
vim api/.env  # lub użyj swojego edytora
```

**Dodaj swój API key:**

```env
# OpenAI Configuration
OPENAI_API_KEY=sk-proj-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
OPENAI_MODEL=gpt-4o-mini

# AI Service Configuration
# Zmień z 'mock' na 'real' aby używać prawdziwego OpenAI
AI_SERVICE=real
```

---

### **Krok 2: Sprawdź konfigurację**

**Plik:** `api/config/services.php`

```php
'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    'url' => env('OPENAI_URL', 'https://api.openai.com/v1/chat/completions'),
],
```

✅ To jest już skonfigurowane!

---

### **Krok 3: Uruchom serwery**

```bash
# Uruchom Docker
docker-compose up -d

# Sprawdź czy działa
docker-compose ps
```

---

## 🧪 Testowanie

### **Metoda 1: Przez API Endpoint**

```bash
# 1. Wygeneruj film
curl -X POST http://localhost:8000/api/v1/generate \
  -H "Content-Type: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "entity_id": "test-movie-slug"
  }'

# Odpowiedź:
# {
#   "job_id": "uuid-here",
#   "status": "PENDING",
#   "message": "Generation queued for movie by slug",
#   "slug": "test-movie-slug"
# }

# 2. Sprawdź status joba
curl http://localhost:8000/api/v1/jobs/{job_id}

# 3. Sprawdź czy film został utworzony
curl http://localhost:8000/api/v1/movies/test-movie-slug
```

---

### **Metoda 2: Przez Tinker (Laravel CLI)**

```bash
# Uruchom Tinker
docker-compose exec php php artisan tinker

# W Tinker:
$client = app(\App\Services\OpenAiClientInterface::class);
$response = $client->generateMovie('the-matrix-1999');

# Sprawdź odpowiedź
print_r($response);

# Wyjście powinno zawierać:
# [
#     'success' => true,
#     'title' => 'The Matrix',
#     'release_year' => 1999,
#     'director' => 'Lana Wachowski, Lilly Wachowski',
#     'description' => '...',
#     'genres' => ['Sci-Fi', 'Action'],
#     'model' => 'gpt-4o-mini',
# ]
```

---

### **Metoda 3: Przez Testy**

```bash
# Uruchom testy dla OpenAiClient
docker-compose exec php php artisan test --filter OpenAiClientTest

# Lub uruchom wszystkie testy
docker-compose exec php php artisan test
```

---

### **Metoda 4: Sprawdź Logi**

```bash
# Sprawdź logi Laravel
docker-compose exec php tail -f storage/logs/laravel.log

# Lub przez Horizon (jeśli używasz queue)
docker-compose logs -f horizon
```

---

## 📊 Co się dzieje po wywołaniu API?

### **Flow z `AI_SERVICE=real`:**

```
1. Client → POST /api/v1/generate
   ↓
2. GenerateController → event(new MovieGenerationRequested())
   ↓
3. QueueMovieGenerationJob Listener
   ↓
4. RealGenerateMovieJob (dispatched to queue)
   ↓
5. OpenAiClient → OpenAI API
   ↓
6. Response parsed → Movie created
   ↓
7. Cache updated → Status: DONE
```

---

## 🔍 Troubleshooting

### **Problem: "OpenAI API key not configured"**

**Rozwiązanie:**
```bash
# Sprawdź czy .env ma klucz
grep OPENAI_API_KEY api/.env

# Sprawdź czy config czyta klucz
docker-compose exec php php artisan tinker
>>> config('services.openai.api_key')
```

---

### **Problem: "Insufficient quota"**

**Rozwiązanie:**
1. Sprawdź billing: https://platform.openai.com/account/billing
2. Dodaj środki jeśli brakuje
3. Sprawdź usage limits

---

### **Problem: "Rate limit exceeded"**

**Rozwiązanie:**
- OpenAI ma limity requestów/minutę
- Dodaj retry logic (już jest w `RealGenerateMovieJob`)
- Lub użyj `AI_SERVICE=mock` dla testów bez limitu

---

### **Problem: Response timeout**

**Rozwiązanie:**
```php
// W OpenAiClient.php jest już ustawione:
private const DEFAULT_TIMEOUT = 60; // sekund

// Możesz zwiększyć jeśli potrzebujesz:
private const DEFAULT_TIMEOUT = 120;
```

---

### **Problem: Koszty za wysokie**

**Rozwiązanie:**
1. Użyj `gpt-4o-mini` (najtańszy)
2. Ustaw limit billing w OpenAI dashboard
3. Użyj `AI_SERVICE=mock` dla lokalnych testów

---

## 💰 Szacunkowe koszty

### **Przykład: Generowanie 100 filmów**

**Każdy request:**
- Input: ~200 tokenów (prompt)
- Output: ~500 tokenów (odpowiedź JSON)
- **Razem: ~700 tokenów/request**

**100 requestów:**
- Input: 100 × 200 = 20K tokenów = **$0.01**
- Output: 100 × 500 = 50K tokenów = **$0.08**
- **Łącznie: ~$0.09**

**Wniosek:** Bardzo tanie! 💰

---

### **🆓 Darmowe testy:**

| Metoda | Koszt | Jakość danych | Użycie |
|--------|------|---------------|--------|
| **Mock AI** (`AI_SERVICE=mock`) | ✅ Darmowe | Symulowane | Testowanie logiki |
| **OpenAI Free Credits** | ✅ Darmowe (jeśli masz) | Prawdziwe | Testowanie integracji |
| **Real API** (pay-as-you-go) | 💰 ~$0.09/100 filmów | Prawdziwe | Production |

**Rekomendacja:**
1. **Początek:** Użyj `AI_SERVICE=mock` do testowania logiki
2. **Integracja:** Sprawdź czy masz darmowe kredyty OpenAI
3. **Production:** Przełącz na `AI_SERVICE=real` gdy gotowe

---

## ✅ Checklist przed testami

- [ ] Konto OpenAI utworzone
- [ ] Sprawdzone darmowe kredyty w Settings → Billing
- [ ] Billing dodany (karta/PayPal) - jeśli brak kredytów
- [ ] API key wygenerowany
- [ ] API key dodany do `api/.env`
- [ ] `AI_SERVICE=real` w `.env` (lub `mock` dla darmowych testów)
- [ ] Docker uruchomiony (`docker-compose up -d`)
- [ ] Queue worker uruchomiony (Horizon lub `queue:work`)
- [ ] Test request wysłany

---

## 🆓 Testowanie bez kosztów (Darmowe)

### **Opcja 1: Mock AI (Rekomendowane dla początkowych testów)**

Użyj `AI_SERVICE=mock` w `api/.env`:

```env
AI_SERVICE=mock  # Zamiast 'real'
```

**Zalety:**
- ✅ Całkowicie darmowe
- ✅ Szybkie (symuluje odpowiedź w 3 sekundy)
- ✅ Idealne do testowania logiki aplikacji
- ✅ Nie wymaga API key

**Kiedy użyć real:**
- Gdy chcesz przetestować rzeczywistą integrację z OpenAI
- Gdy masz darmowe kredyty lub gotówkę na koncie

---

### **Opcja 2: Sprawdź darmowe kredyty OpenAI**

1. Zaloguj się na: https://platform.openai.com/
2. Przejdź do: **Settings → Billing**
3. Sprawdź: **Usage** lub **Credits**
4. Jeśli masz kredyty → możesz testować bez płatności!

**Typowe promocje:**
- 🎁 $5-10 dla nowych kont
- 🎁 Specjalne promocje edukacyjne
- 🎁 Promocje dla developerów

---

## 🔗 Przydatne linki

- **OpenAI Platform:** https://platform.openai.com/
- **API Keys:** https://platform.openai.com/api-keys
- **Billing:** https://platform.openai.com/account/billing
- **Usage Dashboard:** https://platform.openai.com/usage
- **Pricing:** https://openai.com/api/pricing/
- **Documentation:** https://platform.openai.com/docs/

---

**Ostatnia aktualizacja:** 2025-11-01  
**Status:** ✅ Gotowe do użycia

