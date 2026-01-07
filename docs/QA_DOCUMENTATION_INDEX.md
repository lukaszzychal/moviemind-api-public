# 📚 MovieMind API - Dokumentacja QA

> **Ostatnia aktualizacja:** 2025-12-28  
> **Przeznaczenie:** Indeks wszystkich dokumentów QA dla testerów

---

## 🎯 Główne przewodniki testowe

### 1. **Główny przewodnik testów manualnych**
📄 `docs/MANUAL_TESTING_GUIDE.md`

**Najważniejszy dokument** - kompletny przewodnik testowania wszystkich funkcji API:
- Movies API (search, retrieve, refresh, relationships)
- People API (search, retrieve, refresh)
- TV Series & TV Shows API
- Generate API (AI generation)
- Jobs API (async job tracking)
- Reports (Movie, Person, TV Series, TV Show)
- Rate Limiting
- Health & Admin endpoints
- Security verification
- Performance testing

**Dla kogo:** Wszyscy testerzy QA

---

### 2. **Przewodnik testowania API**
📄 `docs/API_TESTING_GUIDE.md`

Szybki start do testowania API:
- Import kolekcji Postman
- Skrypty testowe (Bash, Node.js)
- Przykłady curl
- Scenariusze testowe
- Kody odpowiedzi

**Dla kogo:** Testerzy rozpoczynający pracę z API

---

## 📋 Specjalistyczne przewodniki QA

### 3. **Testy TV Series & TV Shows - zaawansowane endpointy**
📄 `docs/TESTING_TV_SERIES_ADVANCED_ENDPOINTS.md`

Dedykowany przewodnik dla zaawansowanych endpointów TV Series/Shows:
- Related (powiązane serie/show)
- Refresh (odświeżanie z TMDb)
- Report (raportowanie błędów)
- Compare (porównywanie)

**Dla kogo:** Testerzy testujący funkcjonalności TV Series/Shows

---

### 4. **Testy weryfikacji TMDb dla TV Series & TV Shows**
📄 `docs/TESTING_TMDB_VERIFICATION_TV_SERIES_TV_SHOWS.md`

Instrukcje testowania integracji z TMDb:
- Weryfikacja danych z TMDb
- Snapshot management
- Refresh flow
- Error handling

**Dla kogo:** Testerzy testujący integrację z TMDb

---

### 5. **Webhook System - przewodnik QA**
📄 `docs/qa/WEBHOOK_SYSTEM_QA_GUIDE.md`

Kompletny przewodnik testowania systemu webhooków:
- Webhook events
- Retry mechanism
- Error handling
- Idempotency
- Test scenarios

**Dla kogo:** Testerzy testujący system webhooków

---

### 6. **Webhook System - testy manualne**
📄 `docs/qa/WEBHOOK_SYSTEM_MANUAL_TESTING.md`

Instrukcje manualnego testowania webhooków:
- Setup webhook endpoint
- Test cases
- Verification steps
- Troubleshooting

**Dla kogo:** Testerzy wykonujący manualne testy webhooków

---

### 7. **AI Metrics Monitoring - przewodnik QA**
📄 `docs/qa/AI_METRICS_MONITORING_QA_GUIDE.md`

Przewodnik testowania monitoringu metryk AI:
- Token usage
- Parsing accuracy
- Error statistics
- Format comparison

**Dla kogo:** Testerzy testujący monitoring AI

---

## 📊 Raporty testów

### 8. **Raport testów TV Series & TV Shows**
📄 `docs/QA_TEST_REPORT_TV_SERIES_SHOWS.md`

Wyniki testów zaawansowanych endpointów TV Series/Shows:
- Test summary
- Issues found
- Recommendations

**Dla kogo:** QA lead, zespół deweloperski

---

### 9. **Wyniki testów TV Series - zaawansowane endpointy**
📄 `docs/TESTING_RESULTS_TV_SERIES_ADVANCED_ENDPOINTS.md`

Szczegółowe wyniki testów:
- Test execution results
- Pass/fail status
- Issues and fixes

**Dla kogo:** QA lead, testerzy

---

### 10. **Wyniki testów staging - 1.0.3**
📄 `docs/STAGING_TEST_RESULTS_1.0.3_FINAL.md`

Wyniki testów na środowisku staging:
- Deployment verification
- Endpoint testing
- Issues found
- Recommendations

**Dla kogo:** QA lead, DevOps

---

### 11. **Wyniki testów lokalnych - AI Real**
📄 `docs/LOCAL_TEST_RESULTS_AI_REAL.md`

Wyniki testów lokalnych z rzeczywistym AI:
- Configuration verification
- AI generation tests
- Performance metrics

**Dla kogo:** Testerzy, deweloperzy

---

## 🔍 Szczegółowe przewodniki testowe

### 12. **Testy Movies**
📄 `docs/MANUAL_TESTING_MOVIES.md`

Szczegółowy przewodnik testowania Movies API:
- Search functionality
- Movie retrieval
- Refresh operations
- Relationships

**Dla kogo:** Testerzy testujący Movies API

---

### 13. **Testy People**
📄 `docs/MANUAL_TESTING_PEOPLE.md`

Szczegółowy przewodnik testowania People API:
- People search
- Person retrieval
- Bio generation
- Refresh operations

**Dla kogo:** Testerzy testujący People API

---

### 14. **Testy Relationships**
📄 `docs/MANUAL_TESTING_RELATIONSHIPS.md`

Przewodnik testowania relacji między filmami:
- Related movies
- Relationship types
- Filtering
- Sorting

**Dla kogo:** Testerzy testujący relationships

---

### 15. **Testy Person Reports**
📄 `docs/PERSON_REPORTS_TESTING_SUMMARY.md`

Podsumowanie testów raportowania błędów dla osób:
- Report creation
- Admin verification
- Bio regeneration

**Dla kogo:** Testerzy testujący reports

---

## 🛠️ Narzędzia testowe

### 16. **Kolekcja Postman**
📄 `docs/MovieMind_API.postman_collection.json`

Kompletna kolekcja Postman z wszystkimi endpointami:
- Wszystkie endpoints
- Przykładowe requests
- Variables setup

**Dla kogo:** Wszyscy testerzy

---

### 17. **OpenAPI Specification**
📄 `docs/openapi.yaml`

Specyfikacja OpenAPI/Swagger:
- Wszystkie endpoints
- Request/response schemas
- Authentication

**Dla kogo:** Testerzy, deweloperzy, integratorzy

---

## 📝 Szablony i checklisty

### 18. **Szablon raportu testów**
📄 `docs/TESTING_RESULTS_TV_SERIES_ADVANCED_ENDPOINTS.md` (template)

Szablon do dokumentowania wyników testów:
- Test summary
- Issues found
- Recommendations

**Dla kogo:** Testerzy dokumentujący wyniki

---

### 19. **Scenariusze testowe**
📄 `docs/TEST_SCENARIOS.md`

Zbiór scenariuszy testowych:
- Happy paths
- Edge cases
- Error scenarios

**Dla kogo:** Testerzy planujący testy

---

## 🔧 Deployment i środowiska

### 20. **Przewodnik wdrożenia na Railway**
📄 `docs/RAILWAY_DEPLOYMENT.md`

Instrukcje wdrażania na Railway:
- Deployment process
- Migrations
- Verification
- Troubleshooting

**Dla kogo:** DevOps, testerzy weryfikujący deployment

---

## 🚀 Szybki start dla QA

### Krok 1: Przeczytaj główny przewodnik
👉 `docs/MANUAL_TESTING_GUIDE.md` - **START TUTAJ**

### Krok 2: Zaimportuj kolekcję Postman
👉 `docs/MovieMind_API.postman_collection.json`

### Krok 3: Ustaw zmienne środowiskowe
- Lokalnie: `http://localhost:8000`
- Staging: `https://moviemind-api-staging.up.railway.app`

### Krok 4: Rozpocznij testy
1. Przetestuj podstawowe endpointy (Movies, People)
2. Przetestuj Generate API
3. Przetestuj zaawansowane endpointy (Relationships, Reports)
4. Przetestuj TV Series & TV Shows

### Krok 5: Dokumentuj wyniki
Użyj szablonu z `docs/TESTING_RESULTS_TV_SERIES_ADVANCED_ENDPOINTS.md`

---

## 📍 Lokalizacja plików

```
docs/
├── MANUAL_TESTING_GUIDE.md              # ⭐ Główny przewodnik
├── API_TESTING_GUIDE.md                 # Szybki start
├── TESTING_TV_SERIES_ADVANCED_ENDPOINTS.md
├── TESTING_TMDB_VERIFICATION_TV_SERIES_TV_SHOWS.md
├── MANUAL_TESTING_MOVIES.md
├── MANUAL_TESTING_PEOPLE.md
├── MANUAL_TESTING_RELATIONSHIPS.md
├── QA_TEST_REPORT_TV_SERIES_SHOWS.md
├── STAGING_TEST_RESULTS_1.0.3_FINAL.md
├── LOCAL_TEST_RESULTS_AI_REAL.md
├── qa/
│   ├── WEBHOOK_SYSTEM_QA_GUIDE.md
│   ├── WEBHOOK_SYSTEM_MANUAL_TESTING.md
│   ├── AI_METRICS_MONITORING_QA_GUIDE.md
│   └── WEBHOOK_SYSTEM_TEST_RESULTS.md
├── openapi.yaml                          # OpenAPI spec
└── MovieMind_API.postman_collection.json # Postman collection
```

---

## 🎯 Rekomendacje dla testerów

### Dla nowych testerów:
1. Zacznij od `MANUAL_TESTING_GUIDE.md`
2. Zaimportuj kolekcję Postman
3. Przetestuj podstawowe endpointy
4. Przejdź do zaawansowanych funkcji

### Dla doświadczonych testerów:
1. Użyj dedykowanych przewodników dla konkretnych funkcji
2. Przeglądaj raporty testów dla znanych problemów
3. Dokumentuj nowe scenariusze testowe

### Dla QA Lead:
1. Przeglądaj raporty testów
2. Weryfikuj coverage testów
3. Planuj testy na podstawie `TEST_SCENARIOS.md`

---

## 📞 Wsparcie

W razie pytań lub problemów:
- Sprawdź sekcję Troubleshooting w `MANUAL_TESTING_GUIDE.md`
- Sprawdź dokumentację techniczną w `docs/knowledge/`
- Sprawdź deployment guide: `docs/RAILWAY_DEPLOYMENT.md`

---

**Ostatnia aktualizacja:** 2025-12-28

