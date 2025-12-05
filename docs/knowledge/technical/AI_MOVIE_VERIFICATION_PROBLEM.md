# Problem weryfikacji istnienia filmów przez AI

> **Data utworzenia:** 2025-12-01  
> **Kontekst:** Analiza problemu z weryfikacją istnienia filmów przez AI i rekomendacje rozwiązań  
> **Kategoria:** technical  
> **Priorytet:** 🔴 Krytyczny

## 🎯 Problem

### Obecna sytuacja

System MovieMind API ma poważny problem z weryfikacją istnienia filmów:

1. **Endpoint zwraca 202 z job_id** gdy film nie istnieje w naszej bazie danych
2. **Job próbuje wygenerować film przez AI**
3. **AI weryfikuje istnienie filmu w swojej wiedzy z treningu**
4. **AI zwraca `{"error": "Movie not found"}`** nawet dla filmów które istnieją w rzeczywistości
5. **Job kończy się statusem `FAILED` z błędem `NOT_FOUND`**

### Przykład problemu

**Film "Bad Boys" (Will Smith, Martin Lawrence):**
- Film istnieje w rzeczywistości (ma kilka części: Bad Boys, Bad Boys II, Bad Boys for Life)
- Slug: `bad-boys`
- Endpoint: `GET /api/v1/movies/bad-boys` → zwraca `202` z `job_id`
- Job: `GET /api/v1/jobs/{job_id}` → zwraca `FAILED` z `NOT_FOUND`

**Dlaczego to się dzieje:**
- AI (OpenAI) ma wiedzę z treningu danych, ale:
  - Może nie rozpoznać niejednoznacznych slugów (np. "bad-boys" może oznaczać różne filmy)
  - Może nie mieć wiedzy o wszystkich filmach (szczególnie mniej popularnych)
  - Może mieć "zamrożoną" wiedzę (do daty zakończenia treningu)
  - Może "hallucinować" (tworzyć nieprawdziwe informacje)

### Wpływ na użytkownika

**System jest obecnie nie do użycia dla:**
- Filmów które istnieją, ale AI ich nie rozpoznaje
- Niejednoznacznych slugów (np. "bad-boys", "the-matrix")
- Nowych filmów (po dacie treningu AI)
- Niszowych/mało znanych filmów

## 🔍 Analiza przyczyn

### 1. Brak weryfikacji przed generowaniem

**Obecny flow:**
```
Request → Check DB → Not found → Queue Job → AI verifies → FAILED
```

**Problem:** Weryfikacja przez AI następuje dopiero w jobie, po zwróceniu 202.

### 2. AI nie ma dostępu do zewnętrznych baz danych

**Obecne podejście:**
- AI używa tylko swojej wiedzy z treningu
- Brak integracji z TMDb, IMDb, czy innymi bazami danych
- Brak weryfikacji w czasie rzeczywistym

### 3. Niejednoznaczne slugi

**Problem:**
- Slug "bad-boys" może oznaczać:
  - "Bad Boys" (1995) - Will Smith, Martin Lawrence
  - "Bad Boys" (1983) - Sean Penn
  - Inne filmy z podobnym tytułem
- AI może nie rozpoznać który film jest zamierzony

## 💡 Rekomendowane rozwiązania

### Rozwiązanie 1: Integracja z TMDb API (Rekomendowane)

**Opis:**
Integracja z [The Movie Database (TMDb) API](https://www.themoviedb.org/documentation/api) do weryfikacji istnienia filmów przed generowaniem przez AI.

**Zalety:**
- ✅ Weryfikacja w czasie rzeczywistym
- ✅ Dostęp do aktualnych danych o filmach
- ✅ Rozwiązywanie niejednoznaczności (możliwość wyboru z listy)
- ✅ Bezpłatne API (z limitami)
- ✅ Duża baza danych filmów

**Wady:**
- ⚠️ Wymaga klucza API TMDb
- ⚠️ Dodatkowe wywołania API (koszt czasu)
- ⚠️ Zależność od zewnętrznego serwisu

**Implementacja:**
1. Utworzenie `TmdbClient` service
2. Weryfikacja przed utworzeniem joba (synchronous check)
3. Jeśli film istnieje w TMDb → queue job
4. Jeśli nie istnieje → zwróć 404 od razu
5. Przekazanie danych z TMDb do AI (context) dla lepszej generacji

**Przepływ:**
```
Request → Check DB → Not found → Check TMDb → Found → Queue Job → AI generates
Request → Check DB → Not found → Check TMDb → Not found → 404
```

### Rozwiązanie 2: OpenAI Functions/Tools (Alternatywa)

**Opis:**
Użycie OpenAI Functions/Tools do wyszukiwania w zewnętrznych API podczas generowania.

**Zalety:**
- ✅ AI może samodzielnie wyszukiwać w TMDb/IMDb
- ✅ Rozwiązywanie niejednoznaczności przez AI
- ✅ Mniej zmian w kodzie (tylko prompt)

**Wady:**
- ⚠️ Wymaga OpenAI Functions/Tools (może nie być dostępne w gpt-4o-mini)
- ⚠️ Więcej wywołań API (koszt)
- ⚠️ Mniej kontroli nad procesem

**Implementacja:**
1. Konfiguracja OpenAI Functions dla TMDb search
2. Aktualizacja promptu w `OpenAiClient`
3. AI używa funkcji do wyszukiwania przed generowaniem

### Rozwiązanie 3: Cache wyników weryfikacji (Uzupełnienie)

**Opis:**
Cache wyników weryfikacji TMDb w Redis, aby uniknąć powtarzających się wywołań.

**Zalety:**
- ✅ Szybsze odpowiedzi
- ✅ Mniej wywołań API TMDb
- ✅ Niższe koszty

**Implementacja:**
1. Cache wyników weryfikacji TMDb (TTL: 24h)
2. Sprawdzenie cache przed wywołaniem TMDb
3. Aktualizacja cache przy nowych weryfikacjach

### Rozwiązanie 4: Disambiguation Service (Dla niejednoznacznych slugów)

**Opis:**
Service do rozwiązywania niejednoznaczności slugów (np. "bad-boys" → lista możliwych filmów).

**Zalety:**
- ✅ Lepsze UX dla niejednoznacznych slugów
- ✅ Możliwość wyboru przez użytkownika
- ✅ Mniej błędnych generacji

**Implementacja:**
1. Wyszukiwanie w TMDb dla slug
2. Jeśli wiele wyników → zwróć listę możliwości
3. Endpoint do wyboru konkretnego filmu
4. Generowanie dla wybranego filmu

## 📋 Plan implementacji (Rekomendowany)

### Faza 1: Podstawowa integracja TMDb (Krytyczna)

**Czas:** 8-12 godzin

1. **Utworzenie TMDb Client:**
   - Service `TmdbClient` z metodą `searchMovie(string $slug): ?array`
   - Konfiguracja API key w `.env`
   - Obsługa błędów i rate limiting

2. **Weryfikacja przed generowaniem:**
   - W `MovieController::show()` - sprawdź TMDb przed queue job
   - Jeśli nie znaleziono w TMDb → zwróć 404 od razu
   - Jeśli znaleziono → queue job z danymi z TMDb

3. **Przekazanie kontekstu do AI:**
   - Przekaż dane z TMDb do AI (title, year, director) w prompt
   - AI używa tych danych do generacji (mniej halucynacji)

4. **Testy:**
   - Testy dla istniejących filmów
   - Testy dla nieistniejących filmów
   - Testy dla niejednoznacznych slugów

### Faza 2: Cache i optymalizacja (Średni priorytet)

**Czas:** 4-6 godzin

1. **Cache wyników TMDb:**
   - Redis cache dla wyników weryfikacji
   - TTL: 24h
   - Inwalidacja cache

2. **Rate limiting:**
   - Ograniczenie wywołań TMDb API
   - Fallback do AI jeśli TMDb niedostępny

### Faza 3: Disambiguation (Niski priorytet)

**Czas:** 6-8 godzin

1. **Disambiguation Service:**
   - Wyszukiwanie wielu wyników dla slug
   - Endpoint do listy możliwości
   - Wybór konkretnego filmu

## 🔗 Powiązane dokumenty

- [OpenAI API Documentation](https://platform.openai.com/docs)
- [TMDb API Documentation](https://www.themoviedb.org/documentation/api)
- [Task: TASK-043 - Integracja TMDb dla weryfikacji filmów](../issue/pl/TASKS.md#task-043)

## 📌 Notatki

- **Krytyczność:** System jest obecnie nie do użycia dla wielu filmów
- **Priorytet:** 🔴 Wysoki - wymaga natychmiastowej naprawy
- **Alternatywy:** Można rozważyć IMDb API, ale TMDb jest bardziej przyjazne dla deweloperów

---

**Ostatnia aktualizacja:** 2025-12-01

