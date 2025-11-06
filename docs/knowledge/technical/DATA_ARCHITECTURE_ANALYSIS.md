# 📊 Analiza Architektury Danych - Data Warehouse/Lake/Mesh/Mart/Lakehouse

**Data:** 2025-01-27  
**Kontekst:** MovieMind API - integracja źródeł prawdy (TMDB, TVMaze) i hybrid AI generation

---

## 📋 **Przegląd Pojęć**

### **1. Data Warehouse (DWH)**

**Definicja:**
Centralne repozytorium danych z **strukturyzowanymi**, **czystymi** i **przekształconymi** danymi z różnych źródeł, zoptymalizowane do analiz i raportowania.

**Charakterystyka:**
- ✅ **Strukturyzowane dane** (tabele, schematy)
- ✅ **ETL proces** (Extract, Transform, Load)
- ✅ **Schema-on-Write** - schemat definiowany przed zapisem
- ✅ **Zoptymalizowane do zapytań** (OLAP - Online Analytical Processing)
- ✅ **Relacyjna struktura** (star/snowflake schema)

**Przykład dla MovieMind:**
```
TMDB API → ETL → Structured Tables:
  - movies (id, title, release_date, genre, rating)
  - people (id, name, birth_date, biography)
  - movie_credits (movie_id, person_id, role)
```

**Zalety:**
- ✅ Szybkie zapytania analityczne
- ✅ Dane są czyste i zweryfikowane
- ✅ Łatwe do raportowania

**Wady:**
- ❌ Wymaga wcześniejszego zdefiniowania schematu
- ❌ Trudne dodanie nowych źródeł danych
- ❌ Kosztowne przechowywanie

---

### **2. Data Lake**

**Definicja:**
Centralne repozytorium przechowujące **surowych danych** w **oryginalnym formacie** (strukturyzowane, półstrukturyzowane, niestrukturyzowane).

**Charakterystyka:**
- ✅ **Wszystkie typy danych** (JSON, CSV, XML, images, videos)
- ✅ **Schema-on-Read** - schemat definiowany podczas odczytu
- ✅ **Raw data** - dane w oryginalnej formie
- ✅ **Elastyczność** - łatwe dodawanie nowych źródeł
- ✅ **Przechowywanie w oryginalnym formacie**

**Przykład dla MovieMind:**
```
S3/HDFS Storage:
  /raw/tmdb/
    - movies.json (raw API responses)
    - people.json
    - credits.json
  /raw/tvmaze/
    - shows.json
    - episodes.json
  /raw/ai-generations/
    - descriptions/
    - bios/
```

**Zalety:**
- ✅ Elastyczność - przechowuj wszystko
- ✅ Tanie przechowywanie (object storage)
- ✅ Łatwe dodanie nowych źródeł
- ✅ Zachowuje oryginalne dane

**Wady:**
- ❌ Może stać się "data swamp" (bałagan danych)
- ❌ Wymaga przetwarzania podczas odczytu
- ❌ Może być wolne dla analiz

---

### **3. Data Mesh**

**Definicja:**
Architektura **decentralizowana** gdzie dane są **własnością domen biznesowych** zamiast centralnej infrastruktury.

**Charakterystyka:**
- ✅ **Domeny własności danych** (Movie Domain, Person Domain, etc.)
- ✅ **Decentralizacja** - każda domena zarządza swoimi danymi
- ✅ **Self-service** - domeny udostępniają dane innym
- ✅ **Federated governance** - wspólne standardy, ale lokalna implementacja
- ✅ **Product thinking** - dane jako produkty

**Przykład dla MovieMind:**
```
Movie Domain:
  - Własne data store (movies, descriptions)
  - API endpoint dla innych domen
  - Własne ETL z TMDB

Person Domain:
  - Własne data store (people, bios)
  - API endpoint dla innych domen
  - Własne ETL z TMDB/TVMaze

AI Generation Domain:
  - Używa danych z Movie i Person domains
  - Generuje opisy/bios
  - Własne data store dla generation history
```

**Zalety:**
- ✅ Skalowalność - każda domena zarządza swoimi danymi
- ✅ Elastyczność - różne technologie per domena
- ✅ Ownership - jasna odpowiedzialność

**Wady:**
- ❌ Kompleksowość - trudne do zarządzania
- ❌ Wymaga dojrzałości organizacyjnej
- ❌ Może prowadzić do duplikacji danych

---

### **4. Data Mart**

**Definicja:**
**Wyspecjalizowana** część data warehouse dla **konkretnej domeny biznesowej** lub **grupy użytkowników**.

**Charakterystyka:**
- ✅ **Subset data warehouse** - część większego DWH
- ✅ **Zoptymalizowane dla konkretnej domeny**
- ✅ **Szybki dostęp** dla użytkowników domeny
- ✅ **Mniejsze i szybsze** niż pełny DWH

**Przykład dla MovieMind:**
```
Data Warehouse (główny):
  - Wszystkie dane z TMDB, TVMaze, AI generations

Data Mart - Movies:
  - Tylko dane o filmach
  - Zoptymalizowane dla movie queries
  - Szybkie dla movie API

Data Mart - People:
  - Tylko dane o osobach
  - Zoptymalizowane dla people queries
  - Szybkie dla people API
```

**Zalety:**
- ✅ Szybki dostęp dla konkretnej domeny
- ✅ Prostsze zapytania (mniej danych)
- ✅ Łatwiejsze zarządzanie

**Wady:**
- ❌ Duplikacja danych (w DWH i Data Mart)
- ❌ Wymaga synchronizacji
- ❌ Może być kosztowne

---

### **5. Data Lakehouse**

**Definicja:**
**Hybryda** Data Lake i Data Warehouse - łączy **elastyczność** Data Lake z **strukturyzowanymi zapytaniami** Data Warehouse.

**Charakterystyka:**
- ✅ **Schema-on-Write i Schema-on-Read** - oba podejścia
- ✅ **Tanie przechowywanie** (jak Data Lake)
- ✅ **Szybkie zapytania** (jak Data Warehouse)
- ✅ **ACID transactions** (jak Data Warehouse)
- ✅ **Wsparcie dla streaming** i batch processing

**Przykład dla MovieMind:**
```
Delta Lake / Apache Iceberg:
  /lakehouse/
    /raw/ (Data Lake)
      - tmdb_raw.json
      - tvmaze_raw.json
    /processed/ (Data Warehouse)
      - movies.parquet (structured)
      - people.parquet (structured)
      - credits.parquet (structured)
```

**Zalety:**
- ✅ Najlepsze z obu światów (Lake + Warehouse)
- ✅ Elastyczność + Wydajność
- ✅ Tanie przechowywanie + Szybkie zapytania

**Wady:**
- ❌ Relatywnie nowa technologia
- ❌ Wymaga doświadczenia z technologiami (Delta Lake, Iceberg)
- ❌ Może być bardziej złożone

---

## 🎯 **Porównanie Architektur**

| Cecha | Data Warehouse | Data Lake | Data Mesh | Data Mart | Data Lakehouse |
|-------|---------------|-----------|-----------|-----------|----------------|
| **Struktura danych** | Strukturyzowane | Wszystkie typy | Zależy od domeny | Strukturyzowane | Wszystkie typy |
| **Schema** | Schema-on-Write | Schema-on-Read | Zależy | Schema-on-Write | Oba |
| **Koszt przechowywania** | Wysoki | Niski | Zależy | Średni | Niski |
| **Szybkość zapytań** | Wysoka | Niska | Zależy | Wysoka | Wysoka |
| **Elastyczność** | Niska | Wysoka | Wysoka | Niska | Wysoka |
| **Złożoność** | Średnia | Niska | Wysoka | Niska | Wysoka |
| **Best for** | Raportowanie | Raw data storage | Duże organizacje | Specific domains | Modern analytics |

---

## 🎬 **Analiza dla MovieMind API**

### **Kontekst projektu:**

**Źródła prawdy:**
- **TMDB** (The Movie Database) - filmy, osoby, kredyty
- **TVMaze** - seriale TV, odcinki
- **Własne dane** - AI-generated descriptions/bios

**Wymagania:**
1. Zbieranie oryginalnych danych z TMDB/TVMaze
2. Hybrid AI generation (70-80% źródło prawdy + 20-30% AI)
3. Zachowanie oryginalnych danych dla weryfikacji
4. Szybki dostęp do danych dla API
5. Historia zmian i wersjonowanie

---

### **Rekomendacja: Data Lakehouse** ✅

**Dlaczego Data Lakehouse?**

#### ✅ **1. Elastyczność przechowywania:**
```
/lakehouse/
  /raw/                          # Data Lake (oryginalne dane)
    /tmdb/
      - movies_raw.json          # Raw API responses
      - people_raw.json
      - credits_raw.json
    /tvmaze/
      - shows_raw.json
      - episodes_raw.json
    /ai-generations/
      - descriptions_raw.json
      - bios_raw.json
      
  /processed/                    # Data Warehouse (structured)
    /movies.parquet              # Structured, optimized
    /people.parquet
    /movie_person.parquet
    /ai_generations.parquet
```

#### ✅ **2. Hybrid AI Generation:**
```
1. AI pobiera 70-80% kontekstu z /processed/ (structured, szybkie)
2. AI generuje 20-30% własnego kontekstu
3. Tworzy unikalny opis na podstawie 100% kontekstu
```

#### ✅ **3. Szybki dostęp dla API:**
- `/processed/` - zoptymalizowane dla szybkich zapytań
- Parquet format - szybkie odczyty
- Możliwość cache'owania w Redis

#### ✅ **4. Zachowanie oryginalnych danych:**
- `/raw/` - oryginalne odpowiedzi API
- Weryfikacja w razie problemów
- Historia zmian

---

### **Alternatywa: Data Warehouse + Data Lake (Hybrid)**

**Jeśli Data Lakehouse jest zbyt złożone:**

```
Data Lake (S3/MinIO):
  /raw/tmdb/
  /raw/tvmaze/
  
Data Warehouse (PostgreSQL):
  /structured/
    movies
    people
    movie_person
    ai_generations
```

**Workflow:**
1. ETL: Raw data → Structured data
2. API: Czyta z Data Warehouse (szybkie)
3. Weryfikacja: Czyta z Data Lake (oryginalne)

---

## 🏗️ **Architektura Proponowana dla MovieMind**

### **Warstwa 1: Raw Data (Data Lake)**

```
Storage: S3 / MinIO / Local Filesystem
Format: JSON (oryginalne API responses)

/raw/
  /tmdb/
    /movies/
      - 123.json (raw movie data)
      - 456.json
    /people/
      - 789.json (raw person data)
    /credits/
      - movie_123_credits.json
  /tvmaze/
    /shows/
    /episodes/
  /timestamps/
    - 2025-01-27_tmdb_sync.json (metadata)
```

**Cel:**
- ✅ Zachowanie oryginalnych danych
- ✅ Weryfikacja w razie problemów
- ✅ Historia zmian

---

### **Warstwa 2: Processed Data (Data Warehouse)**

```
Storage: PostgreSQL / Parquet Files
Format: Structured tables / Parquet

/processed/
  movies (id, title, release_year, tmdb_id, ...)
  people (id, name, birth_date, tmdb_id, ...)
  movie_person (movie_id, person_id, role, ...)
  ai_generations (id, entity_type, entity_id, description, confidence, ...)
```

**Cel:**
- ✅ Szybkie zapytania dla API
- ✅ Zoptymalizowane dla hybrid AI generation
- ✅ Relacje między danymi

---

### **Warstwa 3: API Cache (Redis)**

```
Storage: Redis
Format: JSON (cached responses)

Cache Keys:
  - movie:{slug}:data
  - person:{slug}:data
  - job:{job_id}:status
```

**Cel:**
- ✅ Szybki dostęp dla API
- ✅ Redukcja obciążenia Data Warehouse
- ✅ TTL dla automatycznego refresh

---

## 🔄 **Workflow dla Hybrid AI Generation**

### **Proces generowania opisu:**

```
1. AI Pobiera kontekst (70-80%):
   ├─ Z Data Warehouse (structured)
   │  ├─ Movie data (title, release_year, genres, director)
   │  ├─ People data (actors, director bio)
   │  └─ Credits data (roles, characters)
   └─ Z Data Lake (raw) - jeśli potrzebne dodatkowe info

2. AI Generuje własny kontekst (20-30%):
   └─ Analiza, interpretacja, kreatywność

3. AI Tworzy opis na podstawie 100% kontekstu:
   └─ 70-80% faktów ze źródła prawdy + 20-30% AI creativity
   └─ Rezultat: Unikalny opis bazujący na faktach
```

### **Przykład:**

```php
// Hybrid AI Generation
function generateMovieDescription(Movie $movie): string {
    // 70-80% kontekstu ze źródła prawdy
    $sourceContext = [
        'title' => $movie->tmdb_title,        // Z TMDB
        'release_year' => $movie->release_year, // Z TMDB
        'genres' => $movie->genres,            // Z TMDB
        'director' => $movie->director->name,   // Z TMDB
        'actors' => $movie->actors->pluck('name'), // Z TMDB
        'plot' => $movie->tmdb_overview,       // Z TMDB (oryginalny opis)
    ];
    
    // 20-30% kontekstu AI (kreatywność, interpretacja)
    $aiContext = [
        'tone' => 'engaging',
        'style' => 'modern',
        'focus' => 'emotional impact',
    ];
    
    // 100% kontekstu dla AI
    $fullContext = array_merge($sourceContext, $aiContext);
    
    // AI generuje opis na podstawie pełnego kontekstu
    return $aiClient->generate([
        'source_context' => $sourceContext,  // 70-80%
        'ai_enhancement' => $aiContext,       // 20-30%
        'instruction' => 'Create unique description based on facts',
    ]);
}
```

---

## 📊 **Porównanie dla MovieMind**

| Architektura | Zalety dla MovieMind | Wady dla MovieMind | Rekomendacja |
|-------------|---------------------|-------------------|--------------|
| **Data Warehouse** | ✅ Szybkie zapytania<br>✅ Structured data | ❌ Trudne dodanie źródeł<br>❌ Wysoki koszt | ⚠️ Może być za mało elastyczne |
| **Data Lake** | ✅ Tanie przechowywanie<br>✅ Elastyczność | ❌ Wolne zapytania<br>❌ Może być bałagan | ⚠️ Za mało strukturyzowane |
| **Data Mesh** | ✅ Skalowalność<br>✅ Ownership | ❌ Złożoność<br>❌ Wymaga dojrzałości | ❌ Za wcześnie dla MVP |
| **Data Mart** | ✅ Szybkie dla domeny | ❌ Duplikacja<br>❌ Synchronizacja | ⚠️ Może być częścią większej architektury |
| **Data Lakehouse** | ✅ Elastyczność + Wydajność<br>✅ Raw + Processed<br>✅ Tanie + Szybkie | ❌ Złożoność techniczna | ✅ **Zalecane** |

---

## 🎯 **Rekomendacja Finalna**

### **Dla MovieMind API - Data Lakehouse (Lake + Warehouse Hybrid)**

**Architektura:**

```
┌─────────────────────────────────────────┐
│         TMDB / TVMaze APIs              │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│         ETL Process                     │
│  (Extract, Transform, Load)             │
└──────────────┬──────────────────────────┘
               │
       ┌───────┴────────┐
       │                │
       ▼                ▼
┌──────────────┐  ┌──────────────┐
│  Data Lake   │  │ Data Warehouse│
│  (Raw JSON)  │  │ (Structured)  │
│              │  │               │
│ /raw/tmdb/   │  │ movies table  │
│ /raw/tvmaze/ │  │ people table  │
└──────────────┘  └───────┬───────┘
                         │
                         ▼
              ┌───────────────────────┐
              │   Hybrid AI Generation│
              │   70-80% source truth │
              │   20-30% AI creative  │
              └───────────┬───────────┘
                         │
                         ▼
              ┌───────────────────────┐
              │   API Endpoints        │
              │   (with Redis cache)   │
              └───────────────────────┘
```

**Implementacja:**

1. **Data Lake (MinIO/S3):**
   - Raw JSON responses z TMDB/TVMaze
   - Tanie przechowywanie
   - Historia zmian

2. **Data Warehouse (PostgreSQL):**
   - Structured tables (movies, people, credits)
   - Szybkie zapytania
   - Relacje między danymi

3. **ETL Process:**
   - Scheduled sync z TMDB/TVMaze
   - Transformacja raw → structured
   - Weryfikacja danych

4. **Hybrid AI Generation:**
   - 70-80% kontekstu z Data Warehouse
   - 20-30% kontekstu AI
   - 100% kontekstu dla generowania

---

## 📝 **Plan Implementacji**

### **Faza 1: Data Lake (MVP)**
- [ ] Integracja z TMDB API
- [ ] Integracja z TVMaze API
- [ ] Storage dla raw JSON (MinIO/S3)
- [ ] Scheduled sync jobs

### **Faza 2: Data Warehouse**
- [ ] Structured tables w PostgreSQL
- [ ] ETL process (raw → structured)
- [ ] Weryfikacja i walidacja danych

### **Faza 3: Hybrid AI Generation**
- [ ] Refaktoryzacja AI generation
- [ ] 70-80% kontekstu ze źródła prawdy
- [ ] 20-30% kontekstu AI
- [ ] Testy jakości opisów

### **Faza 4: Optimization**
- [ ] Redis cache dla API
- [ ] Monitoring i analytics
- [ ] Performance tuning

---

**Ostatnia aktualizacja:** 2025-01-27

