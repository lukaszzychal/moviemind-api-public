# AI Metrics Monitoring - QA Testing Guide

> **Dla:** QA Engineers, Testerzy  
> **Cel:** Przewodnik testowania systemu monitoringu metryk AI

## 🧪 Scenariusze testowe

### 1. Test automatycznego zbierania danych

**Cel:** Sprawdzić, czy metryki są zbierane automatycznie przy każdym wywołaniu AI.

**Kroki:**
1. Wywołaj generowanie filmu: `POST /api/v1/generate` z `entity_type: MOVIE`
2. Sprawdź, czy w bazie `ai_generation_metrics` pojawił się nowy rekord
3. Zweryfikuj, czy wszystkie pola są wypełnione:
   - `entity_type` = 'MOVIE'
   - `data_format` = 'JSON'
   - `prompt_tokens` > 0
   - `completion_tokens` > 0
   - `total_tokens` > 0
   - `parsing_successful` = true/false
   - `model` = 'gpt-4o-mini'

**Oczekiwany wynik:**
- Rekord jest tworzony automatycznie
- Wszystkie pola są wypełnione poprawnie

### 2. Test trackingu tokenów

**Cel:** Sprawdzić, czy tokeny są poprawnie wyciągane z odpowiedzi OpenAI.

**Kroki:**
1. Wywołaj generowanie z mockowanym OpenAI (zwraca `usage` w odpowiedzi)
2. Sprawdź, czy `prompt_tokens`, `completion_tokens`, `total_tokens` są zapisane poprawnie

**Oczekiwany wynik:**
- Tokeny są zgodne z odpowiedzią OpenAI

### 3. Test walidacji parsowania

**Cel:** Sprawdzić, czy walidacja parsowania działa poprawnie.

**Scenariusz A: Poprawne dane**
1. Wywołaj generowanie z pełnymi danymi (wszystkie wymagane pola)
2. Sprawdź, czy `parsing_successful` = true
3. Sprawdź, czy `parsing_errors` = null

**Scenariusz B: Błędne dane**
1. Wywołaj generowanie z niepełnymi danymi (brak wymaganych pól)
2. Sprawdź, czy `parsing_successful` = false
3. Sprawdź, czy `parsing_errors` zawiera błędy

**Oczekiwany wynik:**
- Walidacja działa poprawnie dla obu scenariuszy

### 4. Test endpointów API

#### 4.1. Token Usage

**Kroki:**
1. Utwórz kilka metryk z różnymi formatami (JSON, TOON)
2. Wywołaj `GET /api/v1/admin/ai-metrics/token-usage`
3. Sprawdź odpowiedź:
   - Status 200
   - Struktura JSON zgodna z dokumentacją
   - Statystyki są poprawne

**Oczekiwany wynik:**
- Endpoint zwraca poprawne statystyki

#### 4.2. Parsing Accuracy

**Kroki:**
1. Utwórz metryki z różnymi wynikami parsowania (successful/failed)
2. Wywołaj `GET /api/v1/admin/ai-metrics/parsing-accuracy`
3. Sprawdź, czy `accuracy_percent` jest obliczane poprawnie

**Oczekiwany wynik:**
- Dokładność jest obliczana poprawnie

#### 4.3. Error Statistics

**Kroki:**
1. Utwórz metryki z błędami parsowania
2. Wywołaj `GET /api/v1/admin/ai-metrics/errors`
3. Sprawdź, czy tylko błędne rekordy są zwracane

**Oczekiwany wynik:**
- Tylko błędne rekordy są zwracane

#### 4.4. Format Comparison

**Kroki:**
1. Utwórz metryki dla JSON i TOON
2. Wywołaj `GET /api/v1/admin/ai-metrics/comparison`
3. Sprawdź, czy porównanie jest poprawne:
   - `token_savings` - oszczędności tokenów
   - `accuracy` - różnica w dokładności
   - `avg_tokens` - średnie tokeny

**Oczekiwany wynik:**
- Porównanie jest poprawne

### 5. Test generowania raportów

**Cel:** Sprawdzić, czy scheduled job generuje raporty poprawnie.

**Kroki:**
1. Utwórz metryki z różnymi datami (dzisiaj, wczoraj)
2. Uruchom job ręcznie: `php artisan queue:work` lub `GenerateAiMetricsReportJob::dispatch('daily')`
3. Sprawdź, czy raport został wygenerowany w `storage/app/reports/ai-metrics/`
4. Sprawdź zawartość raportu:
   - Struktura JSON jest poprawna
   - Wszystkie sekcje są wypełnione
   - Daty są poprawne

**Oczekiwany wynik:**
- Raport jest generowany poprawnie
- Zawartość jest kompletna

### 6. Test scheduled jobs

**Cel:** Sprawdzić, czy scheduled jobs są poprawnie skonfigurowane.

**Kroki:**
1. Sprawdź konfigurację w `routes/console.php`
2. Uruchom `php artisan schedule:list` - sprawdź, czy job jest w harmonogramie
3. Uruchom `php artisan schedule:run` - sprawdź, czy job się wykonuje

**Oczekiwany wynik:**
- Job jest w harmonogramie
- Job wykonuje się poprawnie

### 7. Test obsługi błędów

**Cel:** Sprawdzić, czy system poprawnie obsługuje błędy.

**Scenariusz A: Błąd API OpenAI**
1. Wywołaj generowanie z błędnym API key
2. Sprawdź, czy metryka jest zapisana z `parsing_successful` = false
3. Sprawdź, czy `parsing_errors` zawiera informację o błędzie

**Scenariusz B: Błąd podczas trackingu**
1. Symuluj błąd podczas zapisu metryki (np. wyłącz bazę danych)
2. Sprawdź, czy główny flow (generowanie) nie jest przerwany
3. Sprawdź logi - powinien być warning o błędzie trackingu

**Oczekiwany wynik:**
- Błędy są obsługiwane gracefully
- Główny flow nie jest przerwany

## 📋 Checklist testowy

### Podstawowe funkcjonalności
- [ ] Metryki są zbierane automatycznie
- [ ] Tokeny są poprawnie wyciągane
- [ ] Walidacja parsowania działa
- [ ] Endpointy API zwracają poprawne dane
- [ ] Raporty są generowane poprawnie

### Edge cases
- [ ] Obsługa błędów API
- [ ] Obsługa błędów podczas trackingu
- [ ] Puste dane (brak metryk)
- [ ] Duże ilości danych (performance)

### Integracja
- [ ] Scheduled jobs działają
- [ ] Autoryzacja działa (Basic Auth)
- [ ] Raporty są zapisywane w storage

## 🐛 Znane problemy

### Brak danych w raportach
- **Problem:** Raporty są puste mimo metryk w bazie
- **Rozwiązanie:** Sprawdź, czy metryki mają poprawne daty (`created_at`)

### Wolne zapytania
- **Problem:** Endpointy są wolne przy dużych ilościach danych
- **Rozwiązanie:** Rozważ agregacje (patrz dokumentacja techniczna)

## 📞 Wsparcie

W razie problemów, skontaktuj się z zespołem deweloperskim.

---

**Ostatnia aktualizacja:** 2025-01-27

