# Wyjaśnienie: DDD vs DRY + abstrakcje (TASK-051 vs TASK-041)

**Data utworzenia:** 2025-01-27  
**Data aktualizacji:** 2025-01-27 (poprawka - prawdziwe DDD vs DRY)  
**Cel:** Wyjaśnienie różnicy między prawdziwym DDD a DRY + abstrakcje w kontekście TASK-051 vs TASK-041

---

## ❓ Pytanie

Dlaczego TASK-041 nazywamy "domenowym", skoro w prawdziwym DDD każdy agregat jest niezależny i duplikacja jest OK?

---

## ⚠️ Ważne wyjaśnienie

**TASK-041 to NIE jest prawdziwe DDD!** To jest **"DRY + abstrakcje"** z nazewnictwem domenowym.

**Prawdziwe DDD wymaga:**
- **Bounded Contexts** - każdy kontekst ma swoje własne modele
- **Aggregates z granicami** - każdy agregat broni swoich granic
- **Niezależne implementacje** - duplikacja między agregatami jest OK!
- **Brak Shared Kernel** - agregaty nie powinny dzielić implementacji

**TASK-041 narusza zasady DDD:**
- Movie i TvSeries dzielą implementację (trait `Sluggable`) - **Shared Kernel!**
- Wspólne interfejsy (`DescribableContent`) - **łączy różne agregaty!**
- Polimorficzne opisy (`ContentDescription`) - **łączy różne agregaty!**

**TASK-051 jest bardziej zgodny z DDD:**
- Każdy agregat (Movie, TvSeries) ma swoją własną implementację
- Duplikacja jest OK (to są różne agregaty!)
- Każdy agregat broni swoich granic

---

## 🔍 Porównanie: Proste vs "DRY + abstrakcje" podejście

### Proste podejście (TASK-051) - Obecna architektura

**Filozofia:** Każdy model jest niezależny, duplikacja kodu jest akceptowalna.

**⚠️ WAŻNE:** To podejście jest **zgodne z DDD** - każdy agregat jest niezależny i broni swoich granic!

```php
// Movie.php - niezależna implementacja (MovieAggregate)
class Movie extends Model {
    public static function generateSlug(...) {
        // Własna implementacja dla Movie
    }
    
    public function descriptions(): HasMany {
        return $this->hasMany(MovieDescription::class);
    }
}

// Person.php - podobna, ale osobna implementacja (PersonAggregate)
class Person extends Model {
    public static function generateSlug(...) {
        // Duplikacja logiki (podobna, ale inna)
        // W DDD to jest OK - to są różne agregaty!
    }
    
    public function bios(): HasMany {
        return $this->hasMany(PersonBio::class);
    }
}

// TvSeries.php - kolejna niezależna implementacja (TvSeriesAggregate)
class TvSeries extends Model {
    public static function generateSlug(...) {
        // Jeszcze jedna implementacja
        // W DDD to jest OK - to jest osobny agregat!
    }
    
    public function descriptions(): HasMany {
        return $this->hasMany(TvSeriesDescription::class);
    }
}
```

**Charakterystyka:**
- ✅ **Zgodne z DDD** - każdy agregat jest niezależny
- ✅ **Duplikacja OK** - różne agregaty mogą mieć podobną logikę
- ✅ **Osobne granice** - każdy agregat broni swoich granic
- ✅ Proste do zrozumienia
- ✅ Szybkie w implementacji
- ⚠️ Duplikacja kodu (ale to jest OK w DDD!)

---

### "DRY + abstrakcje" podejście (TASK-041) - Wspólne abstrakcje

**Filozofia:** Wspólne zachowania są wyodrębnione do interfejsów/traitów (DRY principle).

**⚠️ WAŻNE:** To **NIE jest prawdziwe DDD** - to jest "DRY + abstrakcje"!

```php
// Interfejs domenowy - definiuje zachowanie
interface DescribableContent {
    public function descriptions(): HasMany;
    public function defaultDescription(): HasOne;
}

// Trait domenowy - wspólna implementacja
trait Sluggable {
    public static function generateSlug(...): string {
        // Wspólna implementacja dla wszystkich
    }
    
    public static function parseSlug(string $slug): array {
        // Wspólna implementacja
    }
}

// Interfejs domenowy - relacje z osobami
interface HasPeople {
    public function people(): BelongsToMany;
}

// Movie.php - używa abstrakcji domenowych
class Movie extends Model implements DescribableContent, HasPeople {
    use Sluggable;
    
    // Nie trzeba implementować generateSlug() - jest w traicie
    // Nie trzeba implementować descriptions() - jest w interfejsie
}

// TvSeries.php - używa tych samych abstrakcji
class TvSeries extends Model implements DescribableContent, HasPeople {
    use Sluggable;
    
    // Ta sama logika, bez duplikacji
}

// Person.php - może użyć Sluggable, ale nie DescribableContent
class Person extends Model implements HasPeople {
    use Sluggable;
    
    // bios() zamiast descriptions() - różne zachowanie
    public function bios(): HasMany {
        return $this->hasMany(PersonBio::class);
    }
}
```

**Charakterystyka:**
- ✅ Brak duplikacji (DRY principle)
- ✅ Wspólne abstrakcje (interfejsy, traity)
- ✅ Polimorficzne opisy (opcjonalnie)
- ⚠️ **NIE jest to prawdziwe DDD** - agregaty dzielą implementację
- ⚠️ Więcej abstrakcji (trudniejsze do zrozumienia)
- ⚠️ Więcej warstw
- ⚠️ **Narusza granice agregatów** - Movie i TvSeries dzielą kod

---

## 🎓 Prawdziwe DDD vs DRY + abstrakcje

### Prawdziwe DDD (Domain-Driven Design)

**Kluczowe zasady DDD:**

1. **Bounded Contexts** - każdy kontekst ma swoje własne modele
   - `Movie` w kontekście "Content" może być inne niż `Movie` w kontekście "Billing"
   - Każdy kontekst broni swoich granic

2. **Aggregate Boundaries** - agregaty są niezależne
   - `MovieAggregate` (Movie + MovieDescription + MoviePeople)
   - `TvSeriesAggregate` (TvSeries + TvSeriesDescription + TvSeriesPeople)
   - **Każdy agregat broni swoich granic**

3. **Duplikacja jest OK** - różne agregaty mogą mieć podobną logikę
   - `Movie::generateSlug()` i `TvSeries::generateSlug()` mogą być duplikowane
   - To jest **akceptowalne** w DDD - to są różne agregaty!

4. **Domain Isolation** - domeny nie powinny dzielić implementacji
   - Jeśli Movie i TvSeries to różne agregaty, nie powinny dzielić kodu
   - Każdy agregat ma swoją własną implementację

**Przykład prawdziwego DDD:**
```php
// MovieAggregate - niezależny agregat
class Movie extends Model {
    public static function generateSlug(...) {
        // Własna implementacja dla Movie
    }
}

// TvSeriesAggregate - niezależny agregat
class TvSeries extends Model {
    public static function generateSlug(...) {
        // Duplikacja OK - to jest osobny agregat!
        // Może być podobna, ale jest niezależna
    }
}
```

### DRY + abstrakcje (TASK-041)

**To NIE jest prawdziwe DDD!** To jest zasada DRY (Don't Repeat Yourself) + abstrakcje.

**Charakterystyka:**
- Wspólne interfejsy/traity dla podobnych zachowań
- Brak duplikacji kodu
- **Narusza granice agregatów** - Movie i TvSeries dzielą implementację
- **Shared Kernel** - wspólny kod między agregatami (anty-wzorzec w DDD!)

**Przykład DRY + abstrakcje:**
```php
// Wspólny trait - narusza granice agregatów!
trait Sluggable {
    public static function generateSlug(...): string {
        // Wspólna implementacja - Movie i TvSeries dzielą kod
    }
}

// Movie używa wspólnego traita
class Movie extends Model {
    use Sluggable; // Dzieli implementację z TvSeries
}

// TvSeries używa tego samego traita
class TvSeries extends Model {
    use Sluggable; // Dzieli implementację z Movie
}
```

### Kiedy użyć którego podejścia?

**Prawdziwe DDD (TASK-051):**
- ✅ Gdy Movie i TvSeries to **różne agregaty** w **różnych kontekstach**
- ✅ Gdy każdy agregat ma **swoje własne reguły biznesowe**
- ✅ Gdy **duplikacja jest akceptowalna** (różne domeny)
- ✅ Gdy agregaty muszą **bronić swoich granic**

**DRY + abstrakcje (TASK-041):**
- ✅ Gdy Movie i TvSeries to **ten sam kontekst domenowy**
- ✅ Gdy logika jest **identyczna** (nie tylko podobna)
- ✅ Gdy **duplikacja jest problemem** (maintenance burden)
- ⚠️ **Narusza granice agregatów** - agregaty dzielą kod

---

## 🎯 Dlaczego TASK-041 nazywamy "domenowym"?

**⚠️ WAŻNE:** TASK-041 to **NIE jest prawdziwe DDD** - to jest "DRY + abstrakcje" z nazewnictwem domenowym.

### 1. Interfejsy domenowe (`DescribableContent`, `HasPeople`)

**Dlaczego "domenowe" w nazwie?**
- Definiują **zachowania domenowe** (co może mieć opisy, co może mieć osoby)
- Nie są techniczne (nie `DatabaseModel`, `EloquentModel`)
- Odzwierciedlają **koncepty biznesowe** (treść z opisami, treść z osobami)

**Ale to NIE jest prawdziwe DDD:**
- W prawdziwym DDD każdy agregat ma swoje własne interfejsy
- `MovieAggregate` i `TvSeriesAggregate` nie powinny dzielić interfejsów
- To jest **Shared Kernel** (anty-wzorzec w DDD!)

**Przykład:**
```php
// ❌ NIE domenowe - techniczne
interface EloquentModel {
    public function save(): bool;
}

// ✅ Domenowe - biznesowe
interface DescribableContent {
    public function descriptions(): HasMany;
}
```

### 2. Traity domenowe (`Sluggable`)

**Dlaczego "domenowe" w nazwie?**
- `Sluggable` to **koncept domenowy** (wszystkie treści mają slugi)
- Nie jest techniczny (nie `HasTimestamps`, `HasUuids`)
- Reprezentuje **zachowanie domenowe** (generowanie unikalnych identyfikatorów)

**Ale to NIE jest prawdziwe DDD:**
- W prawdziwym DDD każdy agregat ma swoją własną implementację `generateSlug()`
- `Movie::generateSlug()` i `TvSeries::generateSlug()` mogą być duplikowane
- Wspólny trait **narusza granice agregatów**

**Przykład:**
```php
// ❌ NIE domenowe - techniczne (Laravel)
trait HasUuids {
    // UUID generation
}

// ✅ Domenowe - biznesowe
trait Sluggable {
    // Slug generation - koncept domenowy
}
```

### 3. Polimorficzne opisy (`ContentDescription`)

**Dlaczego "domenowe" w nazwie?**
- Jeden model opisów dla wszystkich typów treści
- Odzwierciedla **koncept domenowy**: "wszystkie treści mogą mieć opisy"
- Nie jest techniczne (nie `PolymorphicRelation`)

**Ale to NIE jest prawdziwe DDD:**
- W prawdziwym DDD każdy agregat ma swoje własne opisy
- `MovieDescription` (część MovieAggregate)
- `TvSeriesDescription` (część TvSeriesAggregate)
- Polimorficzne opisy **łączą różne agregaty** (anty-wzorzec w DDD!)

**Przykład:**
```php
// ❌ NIE domenowe - techniczne podejście
class MovieDescription extends Model {
    // Tylko dla Movie
}

class TvSeriesDescription extends Model {
    // Tylko dla TvSeries
}

// ✅ Domenowe - koncept biznesowy
class ContentDescription extends Model {
    // Wszystkie treści mogą mieć opisy
    // describable_type, describable_id
}
```

### 4. Wspólne repozytoria przez interfejsy

**Dlaczego "domenowe" w nazwie?**
- Interfejsy repozytoriów definiują **operacje domenowe** (nie techniczne)
- `ContentRepository::findBySlug()` to operacja domenowa
- Nie `DatabaseRepository::query()`

**Ale to NIE jest prawdziwe DDD:**
- W prawdziwym DDD każdy agregat ma swoje własne repozytorium
- `MovieRepository` (dla MovieAggregate)
- `TvSeriesRepository` (dla TvSeriesAggregate)
- Wspólne repozytorium **łączy różne agregaty** (anty-wzorzec w DDD!)

**Przykład:**
```php
// ❌ NIE domenowe - techniczne
interface DatabaseRepository {
    public function query(string $sql): Collection;
}

// ✅ Domenowe - biznesowe
interface ContentRepository {
    public function findBySlug(string $slug): ?Model;
    public function search(string $query): Collection;
}
```

---

## 📊 Porównanie: Prawdziwe DDD vs DRY + abstrakcje

| Aspekt | Prawdziwe DDD (TASK-051) | DRY + abstrakcje (TASK-041) |
|--------|-------------------------|----------------------------|
| **Duplikacja kodu** | ✅ Tak (akceptowalna w DDD!) | ❌ Nie (DRY principle) |
| **Granice agregatów** | ✅ Każdy agregat niezależny | ⚠️ Agregaty dzielą kod |
| **Abstrakcje** | ❌ Brak wspólnych abstrakcji | ✅ Interfejsy/Traity |
| **Polimorfizm** | ❌ Brak | ✅ Tak (opcjonalnie) |
| **Koncepty domenowe** | ✅ W kodzie (osobne agregaty) | ✅ Wyraźne (interfejsy) |
| **Zgodność z DDD** | ✅ **Pełna zgodność** | ❌ **Narusza granice agregatów** |
| **Złożoność** | ✅ Niska | ⚠️ Średnia |
| **Czas implementacji** | ✅ Szybki | ⚠️ Wolniejszy |
| **Maintenance** | ⚠️ Duplikacja kodu | ✅ Brak duplikacji |

---

## 🎓 Co to jest DDD (Domain-Driven Design)?

**DDD to sposób myślenia o domenie biznesowej**, nie tylko techniczne wzorce.

### Kluczowe koncepty DDD:

1. **Bounded Contexts** - każdy kontekst ma swoje własne modele
   - `Movie` w kontekście "Content" może być inne niż `Movie` w kontekście "Billing"
   - Każdy kontekst broni swoich granic

2. **Aggregates** - grupy powiązanych encji z granicami
   - `MovieAggregate` (Movie + MovieDescription + MoviePeople)
   - `TvSeriesAggregate` (TvSeries + TvSeriesDescription + TvSeriesPeople)
   - **Każdy agregat broni swoich granic**
   - **Duplikacja między agregatami jest OK!**

3. **Aggregate Boundaries** - agregaty są niezależne
   - Agregaty nie powinny dzielić implementacji
   - Każdy agregat ma swoją własną logikę
   - **Shared Kernel to anty-wzorzec!**

4. **Entities & Value Objects** - obiekty domenowe
   - Entity: `Movie`, `TvSeries` (mają identyfikator)
   - Value Object: `Slug`, `DescriptionText` (nie mają identyfikatora)

5. **Domain Services** - logika domenowa poza encjami
   - `MovieGenerationService` (generowanie opisów dla Movie)
   - `TvSeriesGenerationService` (generowanie opisów dla TvSeries)

6. **Repositories** - abstrakcja dostępu do danych (jeden na agregat)
   - `MovieRepository` (dla MovieAggregate)
   - `TvSeriesRepository` (dla TvSeriesAggregate)
   - **Nie wspólne repozytoria!**

---

## ✅ Czy TASK-041 to DDD?

**NIE!** TASK-041 **NARUSZA zasady DDD**:

❌ **Narusza granice agregatów:**
- Movie i TvSeries dzielą implementację (trait `Sluggable`)
- Wspólne interfejsy (`DescribableContent`) - Shared Kernel (anty-wzorzec!)
- Polimorficzne opisy (`ContentDescription`) - łączy różne agregaty
- Wspólne repozytoria - łączy różne agregaty

✅ **Ma elementy domenowe w nazwie:**
- Interfejsy domenowe (`DescribableContent`, `HasPeople`)
- Traity domenowe (`Sluggable`)
- Polimorficzne relacje (opcjonalnie)
- Wspólne repozytoria

❌ **Brakuje kluczowych elementów DDD:**
- Value Objects (`Slug`, `DescriptionText`)
- Domain Services (osobne dla każdego agregatu)
- Aggregates z granicami (MovieAggregate, TvSeriesAggregate)
- Domain Events (`MovieDescriptionGenerated`)
- Bounded Contexts
- **Niezależne agregaty** (każdy broni swoich granic)

**Wniosek:** TASK-041 to **"DRY + abstrakcje"**, nie DDD. **TASK-051 jest bardziej zgodny z DDD!**

---

## 🔄 Dlaczego TASK-041 nazywamy "domenowym"?

**Bo używa nazewnictwa domenowego, ale NIE jest to prawdziwe DDD:**

1. **Odzwierciedlają koncepty biznesowe** (nie techniczne) - ✅
2. **Wyrażają język domenowy** (`DescribableContent` zamiast `HasDescriptions`) - ✅
3. **Grupują wspólne zachowania domenowe** (wszystkie treści mają opisy) - ⚠️ **Narusza granice agregatów!**
4. **Przygotowują do pełnego DDD** - ❌ **NIE!** To jest anty-wzorzec w DDD (Shared Kernel)

**Prawdziwe DDD wymaga:**
- Osobnych agregatów (MovieAggregate, TvSeriesAggregate)
- Niezależnych implementacji (duplikacja OK!)
- Granic agregatów (każdy broni swoich granic)
- **Brak Shared Kernel** (wspólne interfejsy/traity to Shared Kernel!)

---

## 📝 Przykład: Różnica w myśleniu

### Prawdziwe DDD (TASK-051)
```
"MovieAggregate ma opisy, PersonAggregate ma biografie, TvSeriesAggregate będzie miało opisy"
→ Każdy agregat ma własną implementację
→ Duplikacja kodu jest OK (to są różne agregaty!)
→ Każdy agregat broni swoich granic
→ Zgodne z DDD!
```

### DRY + abstrakcje (TASK-041)
```
"Wszystkie treści (Content) mogą mieć opisy (DescribableContent)"
→ Wspólny interfejs domenowy
→ Brak duplikacji (DRY)
→ Język domenowy w nazwach
→ ⚠️ Narusza granice agregatów (Shared Kernel - anty-wzorzec w DDD!)
```

---

## 🎯 Podsumowanie

**Dlaczego TASK-041 nazywamy "domenowym"?**

1. **Interfejsy domenowe** - definiują zachowania biznesowe, nie techniczne (✅)
2. **Traity domenowe** - reprezentują koncepty domenowe w nazwie (✅)
3. **Polimorficzne opisy** - odzwierciedlają koncept domenowy w nazwie (✅)
4. **Wspólne repozytoria** - operacje domenowe w nazwie (✅)

**Ale to NIE jest prawdziwe DDD!**

**TASK-041 to "DRY + abstrakcje":**
- ✅ Używa nazewnictwa domenowego
- ✅ Odzwierciedla koncepty biznesowe w nazwach
- ❌ **Narusza granice agregatów** (Shared Kernel - anty-wzorzec!)
- ❌ **Agregaty dzielą implementację** (Movie i TvSeries dzielą kod)

**TASK-051 jest bardziej zgodny z DDD:**
- ✅ **Każdy agregat jest niezależny** (MovieAggregate, TvSeriesAggregate)
- ✅ **Duplikacja jest OK** (różne agregaty mogą mieć podobną logikę)
- ✅ **Każdy agregat broni swoich granic**
- ✅ **Brak Shared Kernel** (każdy agregat ma swoją implementację)

**Różnica:**
- **TASK-051 (DDD):** "Każdy agregat robi swoje, duplikacja OK"
- **TASK-041 (DRY):** "Wspólne zachowania są wyodrębnione, brak duplikacji"

---

---

## ✅ Weryfikacja z literatury DDD

**Data weryfikacji:** 2025-01-27

### Potwierdzone zasady DDD:

1. **Duplikacja danych między agregatami jest OK**
   - Każdy agregat może przechowywać własne kopie danych
   - Służy to zachowaniu niezależności i autonomii agregatów
   - Źródło: Microsoft Learn, Bottega DDD materials

2. **Agregaty powinny być niezależne**
   - Każdy agregat broni swoich granic
   - Agregaty unikają bezpośrednich zależności
   - Komunikacja przez zdarzenia domenowe, nie bezpośrednie referencje
   - Źródło: Microsoft Learn, DDD materials

3. **Należy unikać duplikacji logiki biznesowej**
   - Duplikacja danych ≠ duplikacja logiki biznesowej
   - Każdy agregat powinien mieć własne reguły biznesowe
   - Źródło: Bottega DDD materials

### Niepotwierdzone / wymaga interpretacji:

1. **Duplikacja kodu implementacyjnego (metody)**
   - Literatura mówi o duplikacji **danych**, nie **kodu**
   - Jeśli logika jest **identyczna** → może być Shared Kernel (ryzykowne)
   - Jeśli logika jest **podobna ale różna** → duplikacja jest OK (różne agregaty)

2. **Shared Kernel jako anty-wzorzec**
   - Eric Evans w "Domain-Driven Design" opisuje Shared Kernel jako wzorzec
   - Ale podkreśla, że wymaga ścisłej współpracy między zespołami
   - W praktyce często unika się Shared Kernel, bo narusza granice kontekstów

### Wnioski dla MovieMind API:

**Obecna sytuacja:**
- `Movie::generateSlug()` - używa: title, releaseYear, director
- `Person::generateSlug()` - używa: name, birthDate, birthplace
- **Różne implementacje** z podobną strukturą

**Czy duplikacja jest OK?**
- ✅ **TAK** - to są różne agregaty (Movie vs Person)
- ✅ Każdy agregat ma swoją logikę biznesową
- ✅ Duplikacja kodu jest akceptowalna w DDD

**Czy wspólny trait `Sluggable` narusza DDD?**
- ⚠️ **Zależy od implementacji**
- Jeśli logika jest **identyczna** → Shared Kernel (ryzykowne, ale możliwe)
- Jeśli logika jest **różna** → nie powinno być wspólnego traita (narusza granice)

**Rekomendacja:**
- **TASK-051 (duplikacja)** - bardziej zgodny z DDD dla różnych agregatów
- **TASK-041 (wspólne abstrakcje)** - uzasadnione tylko jeśli logika jest **identyczna** i traktujemy Movie/TvSeries jako ten sam koncept domenowy

---

**Ostatnia aktualizacja:** 2025-01-27

