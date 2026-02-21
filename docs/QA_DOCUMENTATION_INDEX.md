# 📚 MovieMind API - Dokumentacja QA

> **Ostatnia aktualizacja:** 2026-02-15  
> **Przeznaczenie:** Centralny indeks dokumentacji testowej.

---

## 🎯 Główne Przewodniki i Strategie

### 1. **Główny Przewodnik Testów Manualnych**
📄 `docs/MANUAL_TESTING_GUIDE.md`
Kompletny przewodnik "od A do Z" po testowaniu manualnym API. Punkt startowy dla każdego nowego testera.

### 2. **Strategia Testów (Test Strategy)**
📄 `docs/qa/TEST_STRATEGY.md`
Dokument opisujący ogólną strategię testowania, podejście do QA, poziomy testów i narzędzia.

### 3. **Plany Testów Manualnych (Detailed Test Cases)**
📄 `docs/qa/MANUAL_TEST_PLANS.md`
Szczegółowe przypadki testowe (TC-XXX) dla wszystkich modułów. Zawiera konkretne kroki, dane wejściowe i oczekiwane rezultaty.

### 4. **Przewodnik Testowania API (Szybki Start)**
📄 `docs/API_TESTING_GUIDE.md`
Skrócony przewodnik techniczny: import kolekcji Postman, skrypty bash/node, kody błędów.

### 5. **Testy Automatyczne**
📄 `docs/qa/AUTOMATED_TESTS.md`
Informacje o strukturze i uruchamianiu testów automatycznych (PHPUnit).

---

## 📋 Specjalistyczne Przewodniki Funkcjonalne (docs/qa/)

### 📊 Monitoring i Metryki
- **AI Metrics Monitoring:** `docs/qa/AI_METRICS_MONITORING_QA_GUIDE.md`  
  Testowanie monitoringu zużycia tokenów AI, parsowania i błędów.

- **Jobs Dashboard Guidance:** `docs/qa/JOBS_DASHBOARD_QA_GUIDE.md`  
  Testowanie dashboardu zadań asynchronicznych (Horizon/Jobs).

### 🔔 Webhooks i Powiadomienia
- **Webhook System:** `docs/qa/WEBHOOK_SYSTEM_QA_GUIDE.md`  
  Kompleksowe testy systemu webhooków (retry, idempotency).

- **Notification Webhooks:** `docs/qa/NOTIFICATION_WEBHOOKS_QA_GUIDE.md`  
  Specyficzne testy dla powiadomień (np. o zakończeniu generowania).

### 🌍 Wielojęzyczność i Dane
- **Multilingual Metadata:** `docs/qa/MULTILINGUAL_METADATA_QA_GUIDE.md`  
  Testowanie wsparcia dla wielu języków i tłumaczeń.

### 📺 TV Series & Shows
- **TV Series Instructions:** `docs/qa/MANUAL_TESTING_INSTRUCTIONS_TV_SERIES.md`  
  Instrukcje specyficzne dla testowania seriali TV.

- **Advanced Endpoints Guide:** `docs/qa/TESTING_TV_SERIES_ADVANCED_ENDPOINTS.md`
  Przewodnik po zaawansowanych endpointach TV Series (Related, Refresh, Report, Compare).

- **TMDb Verification:** `docs/qa/TMDB_VERIFICATION_TV.md`  
  Weryfikacja poprawności danych pobieranych z TMDb dla TV.

### 🛡️ Admin Panel
- **Admin Panel Test Plan:** `docs/qa/ADMIN_PANEL_MANUAL_TEST_PLAN.md`  
  Testy interfejsu panelu administracyjnego (Filament).

---

## 🔧 Techniczne Przewodniki Weryfikacyjne (docs/qa/)

- **PostgreSQL Testing:** `docs/qa/POSTGRESQL_TESTING.md`  
  Testy specyficzne dla bazy danych (indeksy, JSONB, wydajność).

- **TMDB ID Hiding:** `docs/qa/TMDB_ID_HIDDEN_TESTING.md`  
  Weryfikacja czy wewnętrzne ID z TMDb są poprawnie ukrywane przed użytkownikiem końcowym.

---

## 📊 Raporty i Wyniki Testów (docs/qa/ & docs/archive/)

### Aktualne Raporty
- `docs/qa/JOBS_DASHBOARD_MANUAL_TEST_REPORT.md` - Raport z testów dashboardu jobs.
- `docs/qa/WEBHOOK_SYSTEM_TEST_RESULTS.md` - Wyniki testów systemu webhooków.

### Archiwum
Stare i historyczne raporty znajdują się w folderze `docs/archive/`.

---

## 🛠️ Specyfikacje i Narzędzia

- **OpenAPI / Swagger:** `docs/openapi.yaml`
- **Postman Collection:** `docs/MovieMind_API.postman_collection.json`
- **Scenariusze Testowe (Gherkin):** `docs/TEST_SCENARIOS.md`

---

## 📂 Struktura Katalogów

```
docs/
├── MANUAL_TESTING_GUIDE.md     # ⭐ GŁÓWNY PRZEWODNIK
├── API_TESTING_GUIDE.md
├── QA_DOCUMENTATION_INDEX.md   # 👈 Jesteś tutaj
├── qa/                         # 📁 FOLDER QA - WSZYSTKIE SZCZEGÓŁY
│   ├── TEST_STRATEGY.md        # ⭐ Strategia QA
│   ├── MANUAL_TEST_PLANS.md    # ⭐ Przypadki testowe (TC-XXX)
│   ├── AUTOMATED_TESTS.md
│   ├── WEBHOOK_SYSTEM_*, AI_METRICS_*, ADMIN_PANEL_*, itd.
│   └── (Inne specjalistyczne pliki)
├── archive/                    # 📁 Stare raporty
└── (Pliki narzędziowe: openapi.yaml, *.json)
```

---

## 🚀 Jak zacząć?

1. Przeczytaj **MANUAL_TESTING_GUIDE.md**, aby zrozumieć system.
2. Zapoznaj się z **TEST_STRATEGY.md**, aby poznać podejście.
3. Wybierz obszar do testowania i znajdź odpowiedni plik w `docs/qa/` (np. `WEBHOOK_SYSTEM_QA_GUIDE.md`).
4. Użyj **MANUAL_TEST_PLANS.md** do wykonania konkretnych przypadków testowych.




