# Analiza: Czy warto wprowadzać Value Objects i inne elementy DDD?

**Data utworzenia:** 2025-01-27  
**Cel:** Ocena praktyczności wprowadzenia Value Objects i innych elementów DDD do projektu MovieMind API

---

## ❓ Pytanie

Czy warto wprowadzać Value Objects i inne elementy DDD (Domain Services, Aggregates, Domain Events) do projektu MovieMind API?

---

## 🎯 Obecna architektura projektu

### Co już mamy:
- ✅ **Eloquent Models** - Active Record pattern
- ✅ **Repository Pattern** - abstrakcja dostępu do danych
- ✅ **Service Layer** - logika biznesowa
- ✅ **Form Requests** - walidacja wejścia
- ✅ **Events + Jobs** - asynchroniczne operacje
- ✅ **Enums** - typy wyliczeniowe (Locale, ContextTag, etc.)

### Faza projektu:
- 🎯 **MVP** - szybki development, focus na działanie
- 📊 **Prosty stack** - Laravel + Eloquent + PostgreSQL
- 🚀 **Cel:** Działające API na RapidAPI

---

## 📊 Analiza: Value Objects

### Co to są Value Objects?

**Value Objects** to obiekty domenowe, które:
- Nie mają identyfikatora (immutable)
- Są definiowane przez swoje wartości
- Zawierają walidację i logikę biznesową
- Są porównywane przez wartości, nie referencje

**Przykład:**
```php
// ❌ Obecne podejście (primitive obsession)
class Movie extends Model {
    protected $fillable = ['title', 'slug', 'release_year'];
    
    public function setReleaseYear(int $year): void {
        // Walidacja w wielu miejscach
        if ($year < 1888 || $year > date('Y') + 1) {
            throw new InvalidArgumentException('Invalid year');
        }
        $this->release_year = $year;
    }
}

// ✅ Z Value Object
class ReleaseYear {
    private function __construct(private readonly int $value) {
        if ($value < 1888 || $value > date('Y') + 1) {
            throw new InvalidArgumentException('Invalid year');
        }
    }
    
    public static function fromInt(int $value): self {
        return new self($value);
    }
    
    public function toInt(): int {
        return $this->value;
    }
}

class Movie extends Model {
    public function setReleaseYear(ReleaseYear $year): void {
        $this->release_year = $year->toInt();
    }
}
```

### Kiedy warto wprowadzić Value Objects?

#### ✅ Warto, gdy:

1. **Walidacja jest złożona i powtarza się w wielu miejscach**
   - Przykład: `Slug` - walidacja formatu, unikalność, parsowanie
   - Obecnie: `generateSlug()` w każdym modelu (duplikacja)

2. **Wartość ma wiele aspektów (compound value)**
   - Przykład: `Locale` - język + region (pl-PL, en-US)
   - Obecnie: string `'pl-PL'` - łatwo o błąd

3. **Wartość ma zachowanie (behavior)**
   - Przykład: `DescriptionText` - min/max długość, formatowanie
   - Obecnie: string - brak walidacji w modelu

4. **Wartość jest używana w wielu miejscach**
   - Przykład: `Slug` - Movie, Person, TvSeries
   - Obecnie: duplikacja logiki `generateSlug()`

#### ❌ NIE warto, gdy:

1. **Prosta walidacja już działa**
   - Przykład: `release_year` - prosta walidacja w Form Request
   - Value Object byłby over-engineering

2. **Walidacja jest tylko w jednym miejscu**
   - Przykład: `director` - tylko w Movie
   - Value Object nie przyniesie korzyści

3. **Projekt jest w fazie MVP**
   - Focus na działanie, nie na "idealną" architekturę
   - Value Objects dodają złożoność bez natychmiastowych korzyści

---

## 📊 Analiza: Domain Services

### Co to są Domain Services?

**Domain Services** to serwisy domenowe, które:
- Zawierają logikę domenową, która nie pasuje do Entity
- Operują na wielu agregatach
- Nie mają stanu (stateless)

**Przykład:**
```php
// ❌ Obecne podejście (logika w Service)
class MovieService {
    public function generateDescription(Movie $movie, string $locale): void {
        // Logika generowania - czy to domenowa czy infrastrukturalna?
        $prompt = $this->buildPrompt($movie, $locale);
        $text = $this->aiService->generate($prompt);
        // ...
    }
}

// ✅ Z Domain Service
class ContentGenerationDomainService {
    public function generateDescription(
        DescribableContent $content,
        Locale $locale,
        ContextTag $contextTag
    ): DescriptionText {
        // Czysta logika domenowa - jak generować opis
        $prompt = $this->buildPrompt($content, $locale, $contextTag);
        return DescriptionText::fromAiResponse($prompt);
    }
}
```

### Kiedy warto wprowadzić Domain Services?

#### ✅ Warto, gdy:

1. **Logika domenowa nie pasuje do Entity**
   - Przykład: generowanie opisu - operuje na wielu agregatach
   - Obecnie: `MovieService` - mieszanka domeny i infrastruktury

2. **Logika jest używana w wielu miejscach**
   - Przykład: walidacja zgodności slug z danymi TMDb
   - Obecnie: w różnych serwisach

#### ❌ NIE warto, gdy:

1. **Logika jest prosta i już działa**
   - Przykład: `MovieService::create()` - prosta operacja CRUD
   - Domain Service byłby over-engineering

2. **Logika jest infrastrukturalna, nie domenowa**
   - Przykład: wywołanie API OpenAI - to infrastruktura
   - Domain Service nie powinien zawierać infrastruktury

---

## 📊 Analiza: Aggregates

### Co to są Aggregates?

**Aggregates** to grupy powiązanych encji z granicami:
- Mają Aggregate Root (główna encja)
- Bronią spójności w swoich granicach
- Komunikują się przez zdarzenia domenowe

**Przykład:**
```php
// ❌ Obecne podejście (brak granic)
class Movie extends Model {
    public function descriptions(): HasMany {
        return $this->hasMany(MovieDescription::class);
    }
}

// Każdy może modyfikować MovieDescription bezpośrednio
MovieDescription::create(['movie_id' => 1, 'text' => '...']);

// ✅ Z Aggregate
class MovieAggregate {
    private function __construct(
        private Movie $movie,
        private array $descriptions = []
    ) {}
    
    public function addDescription(DescriptionText $text, Locale $locale): void {
        // Walidacja w granicach agregatu
        if ($this->hasDescriptionForLocale($locale)) {
            throw new DomainException('Description already exists');
        }
        $this->descriptions[] = MovieDescription::create(...);
    }
}
```

### Kiedy warto wprowadzić Aggregates?

#### ✅ Warto, gdy:

1. **Potrzebujesz kontroli spójności**
   - Przykład: Movie + MovieDescription - nie można dodać opisu bez filmu
   - Obecnie: Eloquent relacje - brak kontroli

2. **Masz złożone reguły biznesowe**
   - Przykład: nie można usunąć Movie jeśli ma default_description
   - Obecnie: brak kontroli w modelu

3. **Potrzebujesz transakcyjności**
   - Przykład: tworzenie Movie + MovieDescription w jednej transakcji
   - Obecnie: Eloquent to obsługuje, ale bez kontroli domenowej

#### ❌ NIE warto, gdy:

1. **Proste relacje już działają**
   - Przykład: Movie -> MovieDescription - prosta relacja
   - Aggregate byłby over-engineering

2. **Brak złożonych reguł biznesowych**
   - Przykład: CRUD operacje - proste
   - Aggregate nie przyniesie korzyści

---

## 📊 Analiza: Domain Events

### Co to są Domain Events?

**Domain Events** to zdarzenia domenowe, które:
- Reprezentują coś, co się stało w domenie
- Są publikowane przez agregaty
- Są konsumowane przez inne agregaty/serwisy

**Przykład:**
```php
// ❌ Obecne podejście (Laravel Events - infrastrukturalne)
class MovieDescriptionGenerated extends Event {
    public function __construct(public MovieDescription $description) {}
}

// ✅ Z Domain Event
class MovieDescriptionGenerated extends DomainEvent {
    public function __construct(
        public readonly MovieId $movieId,
        public readonly DescriptionText $description,
        public readonly Locale $locale
    ) {}
}
```

### Kiedy warto wprowadzić Domain Events?

#### ✅ Warto, gdy:

1. **Potrzebujesz komunikacji między agregatami**
   - Przykład: MovieDescriptionGenerated -> aktualizacja cache
   - Obecnie: Laravel Events - działa, ale to infrastruktura

2. **Potrzebujesz event sourcing**
   - Przykład: historia zmian opisów
   - Obecnie: nie ma takiej potrzeby

#### ❌ NIE warto, gdy:

1. **Laravel Events już działają**
   - Przykład: `MovieDescriptionGenerated` - działa dobrze
   - Domain Events byłyby duplikacją

2. **Brak potrzeby event sourcing**
   - Przykład: proste API - nie potrzebujemy historii
   - Domain Events byłyby over-engineering

---

## 🎯 Rekomendacja dla MovieMind API

### Obecna faza: MVP

**Zasada:** "Start Simple, Scale When Needed" (z reguł projektu)

### ✅ Warto wprowadzić (krótkoterminowo):

1. **Value Object: `Slug`**
   - ✅ Walidacja jest złożona i powtarza się
   - ✅ Używane w wielu miejscach (Movie, Person, TvSeries)
   - ✅ Rozwiązałoby duplikację `generateSlug()`
   - **Korzyść:** Brak duplikacji, lepsza walidacja
   - **Koszt:** Niski (1-2h)

2. **Value Object: `Locale`** (opcjonalnie)
   - ✅ Ma wiele aspektów (język + region)
   - ✅ Walidacja formatu (pl-PL, en-US)
   - **Korzyść:** Type safety, walidacja
   - **Koszt:** Niski (1h)

### ⚠️ Rozważyć (średnioterminowo):

1. **Value Object: `DescriptionText`**
   - ✅ Ma zachowanie (min/max długość, formatowanie)
   - ⚠️ Obecnie walidacja w Form Request - działa
   - **Korzyść:** Centralna walidacja
   - **Koszt:** Średni (2-3h)

2. **Domain Service: `ContentGenerationDomainService`**
   - ✅ Logika generowania - czy domenowa czy infrastrukturalna?
   - ⚠️ Obecnie `MovieService` - działa
   - **Korzyść:** Separacja domeny od infrastruktury
   - **Koszt:** Średni (3-4h)

### ❌ NIE warto (na razie):

1. **Aggregates**
   - ❌ Proste relacje już działają
   - ❌ Brak złożonych reguł biznesowych
   - ❌ Over-engineering dla MVP
   - **Koszt:** Wysoki (10-15h)
   - **Korzyść:** Niska (brak problemów do rozwiązania)

2. **Domain Events** (osobne od Laravel Events)
   - ❌ Laravel Events już działają
   - ❌ Brak potrzeby event sourcing
   - ❌ Duplikacja funkcjonalności
   - **Koszt:** Średni (4-5h)
   - **Korzyść:** Niska (Laravel Events wystarczają)

3. **Value Objects dla prostych wartości**
   - ❌ `ReleaseYear` - prosta walidacja w Form Request
   - ❌ `Director` - tylko w Movie
   - ❌ Over-engineering
   - **Koszt:** Niski (1h każdy)
   - **Korzyść:** Niska (brak problemów)

---

## 📋 Plan działania

### Faza 1: MVP (obecna)
- ✅ **Zostaw jak jest** - proste rozwiązania działają
- ✅ **Focus na funkcjonalność** - nie na "idealną" architekturę
- ✅ **YAGNI** - nie dodawaj Value Objects "na zapas"

### Faza 2: Po MVP (gdy pojawią się problemy)
- ✅ **Wprowadź `Slug` Value Object** - gdy duplikacja `generateSlug()` stanie się problemem
- ✅ **Wprowadź `Locale` Value Object** - gdy walidacja locale stanie się problemem
- ⚠️ **Rozważ Domain Services** - gdy logika domenowa stanie się złożona

### Faza 3: Skalowanie (gdy projekt rośnie)
- ⚠️ **Rozważ Aggregates** - gdy pojawią się złożone reguły biznesowe
- ⚠️ **Rozważ Domain Events** - gdy pojawi się potrzeba event sourcing
- ⚠️ **Rozważ pełne DDD** - gdy zespół rośnie i potrzebuje lepszej organizacji

---

## 🎯 Wnioski

### Czy warto wprowadzać Value Objects i inne elementy DDD?

**Odpowiedź: Zależy od fazy projektu i problemów**

#### ✅ Warto, gdy:
1. **Pojawiają się konkretne problemy** (duplikacja, brak walidacji)
2. **Projekt rośnie** (zwiększa się złożoność)
3. **Zespół rośnie** (potrzeba lepszej organizacji)

#### ❌ NIE warto, gdy:
1. **Projekt jest w fazie MVP** (focus na działanie)
2. **Obecne rozwiązania działają** (nie naprawiaj, co nie jest zepsute)
3. **Brak konkretnych problemów** (YAGNI - nie dodawaj "na zapas")

### Rekomendacja dla MovieMind API:

**Obecnie (MVP):**
- ❌ **NIE wprowadzaj** Value Objects i innych elementów DDD
- ✅ **Zostaw jak jest** - proste rozwiązania działają
- ✅ **Focus na funkcjonalność** - nie na architekturę

**W przyszłości (gdy pojawią się problemy):**
- ✅ **Wprowadź `Slug` Value Object** - gdy duplikacja stanie się problemem
- ✅ **Wprowadź `Locale` Value Object** - gdy walidacja stanie się problemem
- ⚠️ **Rozważ Domain Services** - gdy logika stanie się złożona

**Zasada:** "Start Simple, Scale When Needed" - wprowadzaj DDD elementy tylko gdy są potrzebne, nie "na zapas".

---

**Ostatnia aktualizacja:** 2025-01-27

