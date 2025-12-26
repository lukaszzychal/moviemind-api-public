# TASK-040: Propozycje i Rekomendacje - Formaty komunikacji z AI

> **Data utworzenia:** 2025-01-27  
> **Zadanie:** TASK-040  
> **Status:** ⏳ PENDING  
> **Priorytet:** 🟡 Średni

---

## Cel

Przygotowanie szczegółowych propozycji implementacji alternatywnych formatów komunikacji z AI (TOON, CSV) w MovieMind API, wraz z analizą ryzyk, korzyści i rekomendacjami.

---

## Analiza obecnego stanu

### Obecne użycie

**Format:** JSON  
**Lokalizacja:** `api/app/Services/OpenAiClient.php`  
**Use case:** Pojedyncze obiekty (jeden film/osoba na raz)  
**Bulk operations:** Brak

### Szacowane koszty

**Założenia:**
- 1000 generacji opisów/miesiąc
- Średnio 50 tokenów na generację (JSON)
- Koszt: $0.15 za 1M tokenów (gpt-4o-mini)

**Obecne koszty:**
- 1000 × 50 = 50,000 tokenów/miesiąc
- Koszt: ~$0.0075/miesiąc (bardzo niski)

**Potencjalne oszczędności z TOON:**
- 50,000 × 0.30 (30% oszczędności) = 15,000 tokenów oszczędności
- Oszczędność: ~$0.00225/miesiąc (minimalna)

**Wniosek:** Dla obecnego użycia (pojedyncze obiekty) oszczędności są minimalne. TOON ma sens dopiero przy bulk operations.

---

## Opcje implementacji

### Opcja 1: Eksperyment z TOON dla tabularnych danych

#### Zakres

- Implementacja konwertera JSON → TOON dla list filmów/osób
- Dodanie feature flag `ai_use_toon_format`
- Testowanie z rzeczywistym API OpenAI (gpt-4o-mini)
- Pomiar rzeczywistych oszczędności tokenów

#### Korzyści

✅ **Oszczędności tokenów**
- 30-50% redukcji tokenów dla tabularnych danych
- Dla bulk operations (100+ obiektów) oszczędności mogą być znaczące

✅ **Niskie ryzyko**
- Można wyłączyć feature flag
- Możliwość rollbacku bez wpływu na produkcję

✅ **Możliwość weryfikacji**
- Przetestować czy gpt-4o-mini dobrze rozumie TOON
- Zmierzyć rzeczywiste oszczędności

#### Ryzyko

⚠️ **Średnie ryzyko**
- LLM nie są trenowane na TOON
- Może wymagać dodatkowych promptów
- Wymaga walidacji z konkretnym modelem

⚠️ **Koszt implementacji**
- Wymaga implementacji konwertera
- Wymaga testów jednostkowych i integracyjnych
- Wymaga dokumentacji

#### Czas

**1-2 tygodnie:**
- Tydzień 1: Implementacja konwertera + testy
- Tydzień 2: Testy z rzeczywistym API + walidacja

#### Koszt implementacji

**Średni:**
- Implementacja: 1-2 dni
- Testy: 1-2 dni
- Dokumentacja: 0.5 dnia
- **Łącznie:** ~3-4 dni robocze

#### Rekomendacja

✅ **REKOMENDOWANE** - jako eksperyment z możliwością rollbacku.

---

### Opcja 2: Eksperyment z CSV dla bulk operations

#### Zakres

- Implementacja konwertera JSON → CSV dla masowych operacji
- Testowanie z rzeczywistym API OpenAI
- Pomiar rzeczywistych oszczędności tokenów

#### Korzyści

✅ **Teoretyczne oszczędności tokenów**
- 40-60% oszczędności tokenów dla bardzo dużych list (teoretycznie)
- Najmniejszy rozmiar dla czystych tabel

#### Ryzyko

❌ **WYSOKIE RYZYKO** - CSV ma poważne problemy:

1. **Problem z kontekstem kolumn**
   - Im dalej od nagłówka, LLM traci kontekst kolumn
   - CSV jest widziany jako długi ciąg danych bez struktury
   - Dla długich list (>50 wierszy) może prowadzić do błędów interpretacji

2. **Wymaga bardzo dokładnych promptów**
   - Musisz dokładnie opisać kolumny w promptach
   - LLM może źle zinterpretować dane bez kontekstu
   - Wymaga dodatkowych instrukcji w system prompt

3. **Może prowadzić do błędów interpretacji**
   - Brak struktury i typowania
   - Wszystko jest stringiem
   - Trudne do debugowania

#### Czas

**1-2 tygodnie** (podobnie jak Opcja 1)

#### Koszt implementacji

**Średni** (podobnie jak Opcja 1)

#### Rekomendacja

❌ **NIEZALECANE** - ryzyko błędów interpretacji jest zbyt wysokie.

**UWAGA:** Na podstawie analizy źródeł (LinkedIn, Medium), CSV jest **NIEZALECANY** dla komunikacji z AI ze względu na problem z kontekstem kolumn.

---

### Opcja 3: Hybrydowe podejście

#### Zakres

- JSON dla pojedynczych obiektów
- TOON dla list (10-50 obiektów)
- CSV dla bulk operations (>50 obiektów) - **NIEZALECANE**

#### Korzyści

✅ **Maksymalne oszczędności**
- Optymalny format dla każdego use case'a
- Maksymalne oszczędności tokenów

#### Ryzyko

⚠️ **Średnie ryzyko**
- Złożoność utrzymania (3 różne formaty)
- Wymaga logiki wyboru formatu
- Więcej miejsc na błędy

⚠️ **CSV dla bulk operations**
- Problem z kontekstem kolumn pozostaje
- Nie rozwiązuje głównego problemu CSV

#### Czas

**3-4 tygodnie:**
- Tydzień 1-2: Implementacja TOON (jak Opcja 1)
- Tydzień 3: Implementacja logiki wyboru formatu
- Tydzień 4: Testy i walidacja

#### Koszt implementacji

**Wysoki:**
- Implementacja: 3-4 dni
- Testy: 2-3 dni
- Dokumentacja: 1 dzień
- **Łącznie:** ~6-8 dni robocze

#### Rekomendacja

⚠️ **NIEZALECANE** - złożoność nie jest uzasadniona, szczególnie że CSV ma poważne problemy.

**Lepsza wersja:** JSON dla pojedynczych obiektów + TOON dla list (bez CSV).

---

### Opcja 4: Czekać na dojrzewanie formatów

#### Zakres

- Monitorowanie rozwoju TOON/CSV w ekosystemie
- Sprawdzenie czy pojawią się biblioteki/parser
- Czekanie na szersze wsparcie

#### Korzyści

✅ **Brak kosztów implementacji teraz**
- Nie wymaga pracy deweloperskiej
- Możemy skorzystać z gotowych rozwiązań w przyszłości

#### Ryzyko

⚠️ **Możemy przegapić oszczędności**
- Jeśli TOON stanie się standardem, będziemy musieli i tak zaimplementować
- Możemy przegapić oszczędności w międzyczasie

#### Czas

**0** (tylko monitoring)

#### Koszt implementacji

**Brak**

#### Rekomendacja

⚠️ **NIEZALECANE** - możemy przegapić oszczędności. Lepsze jest stopniowe wprowadzanie (Opcja 1).

---

## Rekomendacja główna

### Opcja 1 (TOON) z ostrożnym podejściem

**Stopniowe wprowadzanie z możliwością rollbacku:**

#### Faza 1: Implementacja i testy (2 tygodnie)

**Kroki:**

1. **Implementacja konwertera TOON**
   - Utworzenie `ToonConverter` service
   - Implementacja konwersji JSON → TOON
   - Testy jednostkowe

2. **Integracja z OpenAiClient**
   - Dodanie metody `sendRequestWithToon()`
   - Feature flag `ai_use_toon_format`
   - Możliwość wyboru formatu (JSON/TOON)

3. **Testy z rzeczywistym API**
   - Przygotowanie testowych danych (10-20 filmów)
   - Wysłanie do API w JSON i TOON
   - Porównanie rzeczywistych tokenów (używając tokenizera)
   - Testy dokładności parsowania

4. **Walidacja z gpt-4o-mini**
   - ⚠️ **WAŻNE:** Przetestować czy gpt-4o-mini dobrze rozumie TOON
   - Sprawdzić dokładność parsowania
   - Porównać jakość odpowiedzi

**Deliverables:**
- ✅ Konwerter TOON z testami
- ✅ Integracja z OpenAiClient
- ✅ Feature flag
- ✅ Raport z testów (oszczędności tokenów, dokładność parsowania)

#### Faza 2: Walidacja i decyzja (2 tygodnie)

**Kroki:**

1. **Pomiar rzeczywistych oszczędności**
   - Użyć tokenizera (tiktoken dla GPT-4)
   - Zmierzyć rzeczywiste tokeny, nie tylko bajty
   - Porównać z JSON baseline

2. **Testy dokładności parsowania**
   - Wysłać dane w JSON i TOON
   - Porównać dokładność parsowania
   - Sprawdzić jakość odpowiedzi AI

3. **Porównanie z JSON baseline**
   - Oszczędności tokenów
   - Dokładność parsowania
   - Jakość odpowiedzi

4. **Decyzja o wdrożeniu**
   - Jeśli oszczędności >30% i dokładność porównywalna → wdrożyć
   - Jeśli oszczędności <30% lub dokładność spada → pozostać przy JSON

**Kryteria akceptacji:**
- ✅ Oszczędności tokenów >30%
- ✅ Dokładność parsowania ≥95% (porównywalna z JSON)
- ✅ Jakość odpowiedzi AI porównywalna z JSON

**Deliverables:**
- ✅ Raport z walidacji
- ✅ Rekomendacja: wdrożyć czy pozostać przy JSON

#### Faza 3: Wdrożenie (opcjonalnie, jeśli Faza 2 się powiedzie)

**Jeśli testy się powiodły:**

1. **Rozszerzenie użycia TOON**
   - Wszystkie listy filmów/osób
   - Bulk operations
   - RAG (Retrieval Augmented Generation)

2. **Dokumentacja**
   - Zaktualizować dokumentację API
   - Dodać przykłady użycia TOON
   - Dokumentacja feature flag

3. **Monitoring** ✅ ZAIMPLEMENTOWANE
   - ✅ Śledzić zużycie tokenów - automatyczne zbieranie w `OpenAiClient`
   - ✅ Monitorować dokładność parsowania - walidacja względem schema
   - ✅ Śledzić błędy - automatyczne logowanie i zapis do bazy
   - ✅ Endpointy API do analizy: `/api/v1/admin/ai-metrics/*`
   - ✅ Generowanie raportów okresowych (daily, weekly, monthly)
   - ✅ Dokumentacja: biznesowa, techniczna, QA

**Jeśli testy się nie powiodły:**

1. **Rollback**
   - Wyłączyć feature flag
   - Pozostać przy JSON
   - Udokumentować wyniki testów

2. **Dokumentacja**
   - Udokumentować dlaczego TOON nie został wdrożony
   - Zapisać wnioski dla przyszłości

---

## CSV - Ostateczna rekomendacja

### ❌ CSV NIE jest rekomendowany

**Powody:**

1. **Problem z kontekstem kolumn**
   - Im dalej od nagłówka, LLM traci kontekst kolumn
   - Dla długich list (>50 wierszy) może prowadzić do błędów interpretacji

2. **Wymaga bardzo dokładnych promptów**
   - Musisz dokładnie opisać kolumny w promptach
   - LLM może źle zinterpretować dane bez kontekstu

3. **Ryzyko błędów interpretacji**
   - Brak struktury i typowania
   - Wszystko jest stringiem
   - Trudne do debugowania

**Wyjątek:** CSV może być używany tylko dla:
- Eksport danych do Excel/Google Sheets
- Import danych z zewnętrznych źródeł (w formacie CSV)
- Bardzo proste dane tabelaryczne (<10 wierszy)

**Dla komunikacji z AI:** Użyj JSON lub TOON.

---

## Matryca decyzyjna

| Use Case | JSON | TOON | CSV | Rekomendacja |
|----------|------|------|-----|--------------|
| Pojedynczy obiekt | ✅ | ⚠️ | ❌ | **JSON** |
| Lista 10-50 obiektów | ⚠️ | ✅ | ❌ | **TOON** (po testach) |
| Lista >50 obiektów | ⚠️ | ✅ | ❌ | **TOON** (po testach) |
| Zagnieżdżone struktury | ✅ | ❌ | ❌ | **JSON** |
| Komunikacja z API | ✅ | ❌ | ❌ | **JSON** |
| Eksport do Excel | ⚠️ | ❌ | ✅ | **CSV** |
| Import z zewnętrznych źródeł | ⚠️ | ❌ | ⚠️ | Zależy od źródła |

**Legenda:**
- ✅ = Dobry wybór
- ⚠️ = Możliwy, ale nie idealny
- ❌ = Niezalecany

---

## Timeline i zasoby

### Faza 1: Implementacja i testy (2 tygodnie)

**Tydzień 1:**
- Implementacja `ToonConverter` service
- Testy jednostkowe
- Integracja z `OpenAiClient`

**Tydzień 2:**
- Testy z rzeczywistym API
- Pomiar rzeczywistych tokenów
- Walidacja z gpt-4o-mini

### Faza 2: Walidacja i decyzja (2 tygodnie)

**Tydzień 3:**
- Pomiar rzeczywistych oszczędności
- Testy dokładności parsowania

**Tydzień 4:**
- Porównanie z JSON baseline
- Decyzja o wdrożeniu

### Faza 3: Wdrożenie (opcjonalnie, 1 tydzień)

**Jeśli testy się powiodły:**
- Rozszerzenie użycia TOON
- Dokumentacja
- Monitoring

**Jeśli testy się nie powiodły:**
- Rollback
- Dokumentacja wyników

---

## Metryki sukcesu

### Kryteria akceptacji

1. **Oszczędności tokenów**
   - Minimum 30% oszczędności vs JSON
   - Mierzone rzeczywistymi tokenami (nie bajtami)

2. **Dokładność parsowania**
   - Minimum 95% dokładność (porównywalna z JSON)
   - Brak regresji w jakości odpowiedzi

3. **Stabilność**
   - Brak błędów parsowania
   - Porównywalna jakość odpowiedzi AI

### Metryki do śledzenia

1. **Zużycie tokenów**
   - Przed/po zmianie formatu
   - Oszczędności w %

2. **Dokładność parsowania**
   - Błędy parsowania
   - Jakość odpowiedzi AI

3. **Koszty**
   - Koszty API przed/po
   - ROI (Return on Investment)

---

## Ryzyka i mitgacja

### Ryzyko 1: LLM nie rozumie TOON

**Prawdopodobieństwo:** Średnie  
**Wpływ:** Wysoki

**Mitgacja:**
- Przetestować z gpt-4o-mini przed wdrożeniem
- Dodać wyjaśnienie formatu w system prompt
- Mieć plan rollbacku (feature flag)

### Ryzyko 2: Oszczędności są mniejsze niż oczekiwane

**Prawdopodobieństwo:** Średnie  
**Wpływ:** Niski

**Mitgacja:**
- Zmierzyć rzeczywiste tokeny (nie tylko bajty)
- Użyć tokenizera modelu
- Ustalić próg akceptacji (minimum 30%)

### Ryzyko 3: Błędy parsowania

**Prawdopodobieństwo:** Niskie  
**Wpływ:** Średni

**Mitgacja:**
- Testy jednostkowe i integracyjne
- Walidacja danych przed konwersją
- Fallback do JSON w przypadku błędów

---

## Podsumowanie

### Główna rekomendacja

**Opcja 1: Eksperyment z TOON dla tabularnych danych**

**Uzasadnienie:**
- Oszczędności 30-50% tokenów dla list
- Niskie ryzyko (możliwość rollbacku)
- Możliwość weryfikacji przed pełnym wdrożeniem

**Plan działania:**
1. Faza 1: Implementacja i testy (2 tygodnie)
2. Faza 2: Walidacja i decyzja (2 tygodnie)
3. Faza 3: Wdrożenie (opcjonalnie, jeśli testy się powiodły)

### CSV - NIEZALECANY

**Powody:**
- Problem z kontekstem kolumn
- Wymaga bardzo dokładnych promptów
- Ryzyko błędów interpretacji

### Następne kroki

1. ✅ Zatwierdzenie rekomendacji
2. ⏳ Rozpoczęcie Fazy 1 (implementacja)
3. ⏳ Testy i walidacja
4. ⏳ Decyzja o wdrożeniu

---

## Powiązane dokumenty

- [TOON vs JSON vs CSV Analysis](../knowledge/technical/TOON_VS_JSON_VS_CSV_ANALYSIS.md)
- [Format Comparison Article](../knowledge/technical/FORMAT_COMPARISON_ARTICLE.md)
- [AI Format Tutorial](../knowledge/tutorials/AI_FORMAT_TUTORIAL.md)
- [Task TASK-040](../../issue/pl/TASKS.md#task-040)

---

**Ostatnia aktualizacja:** 2025-01-27

