# Implementation: Enums for Locale, ContextTag, and Origin

## Overview

Zaimplementowano system enumów zamiast zwykłych stringów dla:
- **Locale** (`en-US`, `pl-PL`, etc.)
- **ContextTag** (`modern`, `critical`, `humorous`, `DEFAULT`)
- **DescriptionOrigin** (`GENERATED`, `TRANSLATED`)

## Benefits

### 1. Type Safety
- PHP zapewnia type checking na poziomie języka
- IDE może autouzupełniać wartości
- Błędy są wykrywane podczas kompilacji

### 2. Data Integrity
- Niemożliwe wprowadzenie nieprawidłowej wartości
- Walidacja na poziomie aplikacji
- Spójność danych w całym systemie

### 3. Better DX (Developer Experience)
- Autouzupełnianie w IDE
- Podpowiedzi typów
- Refactoring jest bezpieczniejszy

### 4. Documentation
- Wszystkie dostępne wartości są widoczne w jednym miejscu
- Łatwo dodać nowe wartości

## Implementation

### Enum Classes

#### `App\Enums\Locale`
```php
enum Locale: string
{
    case EN_US = 'en-US';
    case PL_PL = 'pl-PL';
    case DE_DE = 'de-DE';
    case FR_FR = 'fr-FR';
    case ES_ES = 'es-ES';
    
    public function language(): string
    public function country(): string
}
```

#### `App\Enums\ContextTag`
```php
enum ContextTag: string
{
    case DEFAULT = 'DEFAULT';
    case MODERN = 'modern';
    case CRITICAL = 'critical';
    case HUMOROUS = 'humorous';
    
    public function label(): string
}
```

#### `App\Enums\DescriptionOrigin`
```php
enum DescriptionOrigin: string
{
    case GENERATED = 'GENERATED';
    case TRANSLATED = 'TRANSLATED';
}
```

### Models with Casts

```php
class MovieDescription extends Model
{
    protected $casts = [
        'locale' => Locale::class,
        'context_tag' => ContextTag::class,
        'origin' => DescriptionOrigin::class,
    ];
}
```

Laravel automatycznie konwertuje:
- **Do bazy**: enum → string (`.value`)
- **Z bazy**: string → enum

### Usage Examples

```php
// Creating with enums
$desc = MovieDescription::create([
    'movie_id' => 1,
    'locale' => Locale::EN_US,              // instead of 'en-US'
    'context_tag' => ContextTag::MODERN,     // instead of 'modern'
    'origin' => DescriptionOrigin::GENERATED, // instead of 'GENERATED'
    'text' => 'Description text',
]);

// Reading - automatically cast to enum
echo $desc->locale->value;        // 'en-US'
echo $desc->locale->language();   // 'English'
echo $desc->context_tag->label(); // 'Modern'

// Comparison
if ($desc->locale === Locale::EN_US) {
    // ...
}

// Getting all values
$allLocales = Locale::values(); // ['en-US', 'pl-PL', ...]
```

## Migration Strategy

### Current State
- Enums są zdefiniowane
- Modele używają casts
- **Seeders jeszcze używają stringów** (wymaga aktualizacji)

### Next Steps
1. ✅ Enums created
2. ✅ Models updated
3. ⏳ Seeders need update to use enums
4. ⏳ Validation rules can use enums
5. ⏳ API requests can validate against enums

## Notes

- **Database storage**: Nadal jako VARCHAR/STRING (enumy są tylko w PHP)
- **Backward compatibility**: Stringi z bazy są automatycznie konwertowane do enumów
- **Adding new values**: Dodaj nowy case w enumie, database nie wymaga zmian (chyba że potrzebujesz constraint)

## Comparison: Enum vs String

| Feature | String | Enum |
|---------|--------|------|
| Type safety | ❌ | ✅ |
| IDE autocomplete | ❌ | ✅ |
| Runtime validation | ⚠️ Manual | ✅ Automatic |
| Refactoring safety | ❌ | ✅ |
| Documentation | ⚠️ Comments | ✅ Code |

