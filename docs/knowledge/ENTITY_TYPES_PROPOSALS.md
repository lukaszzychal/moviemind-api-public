# Propozycje Typów Encji - MovieMind API

**Data utworzenia:** 2025-01-27  
**Status:** 📋 Do rozważenia w przyszłości  
**Autor:** Analiza możliwości rozszerzenia API

---

## 🎯 Obecny Stan

System MovieMind API obecnie obsługuje:
- ✅ **MOVIE** - Filmy
- ✅ **PERSON** - Osoby (aktorzy, reżyserzy, itp.)

---

## 📋 Propozycje Nowych Typów Encji

### 1. TV Series ⭐ **NAJWYŻSZY PRIORYTET**

#### Definicja
- **TV Series** = Serial telewizyjny (produkcja fabularna z sezonami/odcinkami)
  - Przykłady: "Breaking Bad", "Game of Thrones", "Stranger Things", "The Crown"
  - Charakterystyka: ciągła fabuła, powtarzające się postacie, sezony i odcinki
  - TMDb API: `tv` (media_type: "tv", gatunki: Drama, Comedy, Sci-Fi, itp.)

#### ✅ **REKOMENDOWANA NAZWA: `TvSeries`**

**Uzasadnienie:**
1. **Konwencja projektu:**
   - Modele: `Movie`, `Person` (PascalCase, pojedynczy rzeczownik)
   - `TvSeries` pasuje do tej konwencji

2. **Semantyka:**
   - ✅ `TvSeries` = seriale telewizyjne (jasne, precyzyjne)
   - Jasno określa, że to produkcje fabularne z sezonami/odcinkami

3. **Branżowy standard:**
   - "TV Series" jest powszechnie używane w branży dla seriali telewizyjnych

**Struktura nazewnictwa:**
- Model: `TvSeries` (PascalCase)
- Tabela: `tv_series` (snake_case, plural)
- Entity type: `TV_SERIES` (wielkie litery, podkreślenia)
- Opis: `TvSeriesDescription` (kompozycja jak `MovieDescription`)

#### Struktura danych (proponowana)
```sql
tv_series
├── id (PK, UUIDv7)
├── title
├── slug
├── first_air_date
├── last_air_date
├── number_of_seasons
├── number_of_episodes
├── genres (array)
├── tmdb_id
└── default_description_id (FK)

tv_series_descriptions
├── id (PK, UUIDv7)
├── tv_series_id (FK)
├── locale
├── text
├── context_tag
├── origin
├── ai_model
└── created_at
```

---

### 1b. TV Show ⭐ **NAJWYŻSZY PRIORYTET**

#### Definicja
- **TV Show** = Program telewizyjny (nie-fabularny)
  - Przykłady: "The Tonight Show", "Survivor", "Big Brother", "The Daily Show", "60 Minutes"
  - Charakterystyka: talk-show, reality show, programy informacyjne, dokumenty, programy rozrywkowe
  - TMDb API: `tv` (media_type: "tv", gatunki: Talk, Reality, News, Documentary, itp.)

#### ✅ **REKOMENDOWANA NAZWA: `TvShow`**

**Uzasadnienie:**
1. **Konwencja projektu:**
   - Modele: `Movie`, `Person` (PascalCase, pojedynczy rzeczownik)
   - `TvShow` pasuje do tej konwencji

2. **Semantyka:**
   - ✅ `TvShow` = programy telewizyjne (talk-show, reality, news, itp.)
   - Jasno rozróżnia od seriali fabularnych (`TvSeries`)

3. **Branżowy standard:**
   - "TV Show" jest powszechnie używane dla programów telewizyjnych (nie-fabularnych)

**Struktura nazewnictwa:**
- Model: `TvShow` (PascalCase)
- Tabela: `tv_shows` (snake_case, plural)
- Entity type: `TV_SHOW` (wielkie litery, podkreślenia)
- Opis: `TvShowDescription` (kompozycja jak `MovieDescription`)

#### Struktura danych (proponowana)
```sql
tv_shows
├── id (PK, UUIDv7)
├── title
├── slug
├── first_air_date
├── last_air_date
├── number_of_seasons (nullable) -- Nie wszystkie programy mają sezony
├── number_of_episodes (nullable) -- Nie wszystkie programy mają odcinki
├── genres (array)
├── show_type (enum) -- TALK_SHOW, REALITY, NEWS, DOCUMENTARY, VARIETY, GAME_SHOW
├── tmdb_id
└── default_description_id (FK)

tv_show_descriptions
├── id (PK, UUIDv7)
├── tv_show_id (FK)
├── locale
├── text
├── context_tag
├── origin
├── ai_model
└── created_at
```

#### Różnica między TV Series a TV Show

| Aspekt | TV Series | TV Show |
|--------|-----------|---------|
| **Typ produkcji** | Fabularna (scripted) | Nie-fabularna (unscripted) |
| **Przykłady** | Breaking Bad, Game of Thrones | The Tonight Show, Survivor |
| **Fabuła** | Ciągła narracja, postacie | Brak ciągłej fabuły |
| **Sezony/Odcinki** | Zawsze | Często, ale nie zawsze |
| **Gatunki TMDb** | Drama, Comedy, Sci-Fi, Crime | Talk, Reality, News, Documentary |
| **Użycie** | Seriale telewizyjne | Programy telewizyjne |

**Kryterium rozróżnienia:**
- **TvSeries** = produkcje fabularne (scripted) z ciągłą fabułą
- **TvShow** = programy nie-fabularne (unscripted): talk-show, reality, news, dokumenty

**Uwaga:** TMDb API używa jednego endpointu `/tv` dla obu typów, ale różnica jest w gatunkach (`genres`). W MovieMind API rozdzielamy je na dwa modele dla lepszej semantyki i możliwości filtrowania.

#### Kiedy implementować?
**Po zakończeniu:**
- ✅ Stabilizacji funkcji MOVIE i PERSON
- ✅ Zadania związane z weryfikacją TMDb (TASK-044, TASK-037, TASK-038)
- ✅ Podstawowych endpointów i infrastruktury

**Przed:**
- Rozszerzeniem o inne typy encji (Company, Network, itp.)
- Funkcjami zaawansowanymi (webhooks, analytics)

**Rekomendowana pozycja w backlogu:**
- Po TASK-015 (testy Newman)
- Przed TASK-008 (webhooks) - jako naturalne rozszerzenie MVP

#### Priorytet
🔴 **Wysoki** - naturalne rozszerzenie MVP, duże zapotrzebowanie użytkowników

---

### 2. TV Episode

#### Definicja
- Pojedynczy odcinek serialu telewizyjnego
- Zawiera: numer sezonu, numer odcinka, datę emisji
- TMDb API: `tv/{id}/season/{season_number}/episode/{episode_number}`

#### Struktura danych (proponowana)
```sql
tv_episodes
├── id (PK, UUIDv7)
├── tv_series_id (FK)
├── season_number
├── episode_number
├── title
├── air_date
├── tmdb_id
└── default_description_id (FK)

tv_episode_descriptions
├── id (PK, UUIDv7)
├── tv_episode_id (FK)
├── locale
├── text
├── context_tag
├── origin
├── ai_model
└── created_at
```

#### Kiedy implementować?
**Po:**
- Implementacji TV Series (wymaga relacji z TV Series)
- Stabilizacji TV Series

#### Priorytet
🟡 **Średni** - wymaga wcześniejszej implementacji TV Series

---

### 3. Collection ❌ **NIE WYMAGA OSOBNEGO TYPU ENCJI**

#### Status: ✅ **JUŻ ZAIMPLEMENTOWANE**

Collection jest już dostępne w systemie poprzez:
- Endpoint: `GET /api/v1/movies/{slug}/collection`
- Service: `MovieCollectionService`
- Źródło danych: TMDb snapshots (`belongs_to_collection`)

#### Jak działa obecnie?
1. Film ma w TMDb snapshot pole `belongs_to_collection`
2. `MovieCollectionService` znajduje wszystkie filmy w tej samej kolekcji
3. Zwraca kolekcję z listą filmów

#### Wniosek
**Collection NIE wymaga osobnego typu encji** - można pobrać na podstawie relacji między filmami z TMDb snapshots. Obecna implementacja jest wystarczająca.

---

### 4. Company / Studio

#### Definicja
- Wytwórnie filmowe (np. "Marvel Studios", "Warner Bros.")
- Możliwe AI-opisy: historia, portfolio, charakterystyka
- TMDb API: `company/{id}`

#### Struktura danych (proponowana)
```sql
companies
├── id (PK, UUIDv7)
├── name
├── slug
├── headquarters
├── homepage
├── tmdb_id
└── default_description_id (FK)

company_descriptions
├── id (PK, UUIDv7)
├── company_id (FK)
├── locale
├── text
├── context_tag
├── origin
├── ai_model
└── created_at
```

#### Kiedy implementować?
**Po:**
- Implementacji TV Series
- Stabilizacji wszystkich podstawowych typów encji

#### Priorytet
🟢 **Niski** - mniejsza wartość dla AI-opisów, mniejsze zapotrzebowanie użytkowników

---

### 5. Network

#### Definicja
- Sieci telewizyjne (np. "HBO", "Netflix", "BBC")
- Możliwe AI-opisy: profil, historia, charakterystyka
- TMDb API: `network/{id}`

#### Struktura danych (proponowana)
```sql
networks
├── id (PK, UUIDv7)
├── name
├── slug
├── headquarters
├── homepage
├── tmdb_id
└── default_description_id (FK)

network_descriptions
├── id (PK, UUIDv7)
├── network_id (FK)
├── locale
├── text
├── context_tag
├── origin
├── ai_model
└── created_at
```

#### Kiedy implementować?
**Po:**
- Implementacji TV Series (naturalne powiązanie)
- Implementacji Company (podobna struktura)

#### Priorytet
🟢 **Niski** - mniejsza wartość dla AI-opisów, mniejsze zapotrzebowanie użytkowników

---

### 6. Character

#### Definicja
- Postacie z filmów/seriali
- Biografie postaci generowane przez AI
- Może być powiązane z Person (aktor grający postać)

#### Struktura danych (proponowana)
```sql
characters
├── id (PK, UUIDv7)
├── name
├── slug
├── movie_id (FK, nullable)
├── tv_series_id (FK, nullable)
├── person_id (FK, nullable) -- aktor grający postać
└── default_bio_id (FK)

character_bios
├── id (PK, UUIDv7)
├── character_id (FK)
├── locale
├── text
├── context_tag
├── origin
├── ai_model
└── created_at
```

#### Kiedy implementować?
**Po:**
- Stabilizacji MOVIE, PERSON, TV Series
- Ustabilizowaniu relacji między encjami

#### Priorytet
🟢 **Niski** - zaawansowana funkcja, mniejsze zapotrzebowanie

---

### 7. Genre

#### Definicja
- Gatunki filmowe z AI-opisami
- Obecnie: tagi w tabeli `genres`
- Możliwe rozszerzenie: pełne encje z opisami

#### Status
- ✅ Obecnie: Tabela `genres` z relacjami many-to-many
- ❓ Rozszerzenie: Dodanie opisów AI dla gatunków

#### Priorytet
🟢 **Niski** - obecna implementacja wystarczająca, rozszerzenie opcjonalne

---

### 8. Award / Festival

#### Definicja
- Nagrody i festiwale (Oscary, Cannes, itp.)
- Opisy wydarzeń, historii

#### Priorytet
🟢 **Niski** - bardzo niski priorytet, małe zapotrzebowanie

---

### 9. Video Game

#### Definicja
- Gry wideo
- Wymagałoby innego źródła danych niż TMDb (np. IGDB API)

#### Priorytet
🟢 **Niski** - wykracza poza zakres MovieMind API (filmy/seriale)

---

## 📊 Podsumowanie Priorytetów

| Typ Encji | Priorytet | Status | Kiedy? |
|-----------|-----------|--------|--------|
| **TV Series** | 🔴 Wysoki | ⏳ Propozycja | Po stabilizacji MOVIE/PERSON |
| **TV Show** | 🔴 Wysoki | ⏳ Propozycja | Po stabilizacji MOVIE/PERSON (razem z TV Series) |
| **TV Episode** | 🟡 Średni | ⏳ Propozycja | Po TV Series |
| **Collection** | ✅ Zaimplementowane | ✅ Gotowe | - |
| **Company** | 🟢 Niski | ⏳ Propozycja | Po TV Series/TV Show |
| **Network** | 🟢 Niski | ⏳ Propozycja | Po TV Series/TV Show |
| **Character** | 🟢 Niski | ⏳ Propozycja | Po stabilizacji wszystkich typów |
| **Genre** | 🟢 Niski | ✅ Częściowo | Opcjonalne rozszerzenie |
| **Award/Festival** | 🟢 Niski | ⏳ Propozycja | Bardzo niski priorytet |
| **Video Game** | 🟢 Niski | ⏳ Propozycja | Poza zakresem projektu |

---

## 🎯 Rekomendacja

### Najbliższe kroki:
1. **TV Series + TV Show** - najwyższy priorytet jako naturalne rozszerzenie MVP (implementować razem)
2. **TV Episode** - po implementacji TV Series
3. Pozostałe typy - do rozważenia w przyszłości w zależności od zapotrzebowania

### Kiedy implementować TV Series i TV Show?
**Po zakończeniu:**
- ✅ TASK-015 (testy Newman w CI)
- ✅ Stabilizacji wszystkich funkcji MOVIE i PERSON
- ✅ Weryfikacji TMDb (TASK-044, TASK-037, TASK-038)

**Przed:**
- TASK-008 (webhooks) - jako naturalne rozszerzenie MVP
- TASK-009 (Admin UI) - można uwzględnić TV Series i TV Show w UI

**Rekomendowana pozycja w backlogu:**
- Po TASK-015
- Przed TASK-008

**Uwaga:** TV Series i TV Show powinny być implementowane razem, ponieważ:
- Mają podobną strukturę danych (można użyć wspólnych traitów/interfejsów)
- Używają tego samego endpointu TMDb API (`/tv`)
- Różnica jest głównie semantyczna (gatunki), nie strukturalna
- Ułatwia to utrzymanie spójności API

---

## 📝 Uwagi

- **Collection:** Nie wymaga osobnego typu encji - obecna implementacja przez `MovieCollectionService` jest wystarczająca
- **TV Series vs TV Show:** 
  - **TV Series** = seriale telewizyjne (produkcje fabularne z sezonami/odcinkami)
  - **TV Show** = programy telewizyjne (talk-show, reality, news, dokumenty)
  - Oba modele powinny być implementowane razem dla spójności
- **TMDb API:** Wszystkie propozycje (oprócz Video Game) są dostępne w TMDb API
- **Entity types:** `TV_SERIES` i `TV_SHOW` (wielkie litery, podkreślenia) - zgodnie z konwencją projektu (`MOVIE`, `PERSON`)

---

**Ostatnia aktualizacja:** 2025-01-27

