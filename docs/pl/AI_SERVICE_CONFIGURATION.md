# AI Service Configuration – Tryb Mock vs Real

## 🎯 Przegląd
MovieMind obsługuje dwa tryby działania AI sterowane przez zmienną środowiskową `AI_SERVICE`. Od wybranej wartości zależy, który job zostanie wysłany do kolejki i czy wykonamy prawdziwe wywołanie OpenAI.

- **Tryb mock** (`AI_SERVICE=mock`) – deterministyczne dane do demo/CI, bez kosztów API.
- **Tryb real** (`AI_SERVICE=real`) – joby `RealGenerateMovieJob` / `RealGeneratePersonJob` korzystają z `OpenAiClientInterface` i zapisują realne wyniki.

## ⚙️ Szybka konfiguracja

1. **Zmiennie w `.env`**

```env
# Konfiguracja trybu AI
AI_SERVICE=mock            # lub 'real'

# Wymagane tylko przy AI_SERVICE=real
OPENAI_API_KEY=sk-********
OPENAI_MODEL=gpt-4o-mini   # opcjonalna zmiana modelu
OPENAI_URL=https://api.openai.com/v1/chat/completions
```

2. **Wyciąg z `config/services.php`**

```php
'ai' => [
    'service' => env('AI_SERVICE', 'mock'),
],

'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    'url' => env('OPENAI_URL', 'https://api.openai.com/v1/chat/completions'),
],
```

Nie trzeba ręcznie rejestrować serwisów — listener `QueueMovieGenerationJob` korzysta z `AiServiceSelector`, a joby pobierają `OpenAiClientInterface` poprzez wstrzykiwanie zależności.

## 🔁 Jak działa selector

1. Kontrolery emitują eventy `MovieGenerationRequested` / `PersonGenerationRequested`.
2. Listener (`QueueMovieGenerationJob` / `QueuePersonGenerationJob`) wywołuje `AiServiceSelector::getService()`.
3. Selector sprawdza `config('services.ai.service')`:
   - `mock` → dispatch `MockGenerate*Job`.
   - `real` → dispatch `RealGenerate*Job`.
4. `RealGenerate*Job` otrzymuje `OpenAiClientInterface`, wykonuje zapytanie do OpenAI i zapisuje wynik w bazie.

## 🔄 Przełączanie trybów

```bash
# Zmień tryb
echo "AI_SERVICE=real" >> .env
echo "OPENAI_API_KEY=sk-..." >> .env

# Odśwież konfigurację
php artisan config:clear
php artisan queue:restart
```

W środowiskach Docker po zmianie zmiennych zrestartuj kontenery.

## ✅ Rekomendowane scenariusze

| Scenariusz | Zalecane ustawienie | Uwagi |
|------------|--------------------|-------|
| Lokalny development / CI | `AI_SERVICE=mock` | Stabilne wyniki, brak zależności zewnętrznych |
| Demo z prawdziwym AI | `AI_SERVICE=real` + klucz demo | Użyj krótkich promptów i limitów |
| Produkcja | `AI_SERVICE=real` | Przechowuj klucze w managerze sekretów, rotuj je regularnie |

Zawsze utrzymuj tryb mock pod ręką — przydaje się w regresji i pracy offline.

---

**Wersja angielska:** [`../en/AI_SERVICE_CONFIGURATION.md`](../en/AI_SERVICE_CONFIGURATION.md)

