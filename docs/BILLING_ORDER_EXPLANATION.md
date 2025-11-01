# 📋 Billing Order - Wyjaśnienie

**Data:** 2025-11-01

---

## ❓ Co to jest `billing_order`?

`billing_order` to **pozycja w napisach końcowych/czołówce filmu** - określa kolejność wyświetlania osób związanych z filmem.

---

## 🎬 Jak to działa w filmach?

### **Billing Order (Pozycja w napisach):**

| `billing_order` | Znaczenie | Przykład |
|----------------|-----------|----------|
| `1` | Główna gwiazda (lead actor) | Keanu Reeves w "The Matrix" |
| `2` | Drugoplanowy główny | Laurence Fishburne w "The Matrix" |
| `3` | Ważny aktor | Carrie-Anne Moss w "The Matrix" |
| `4+` | Aktorzy drugoplanowi | Wszyscy inni |
| `null` | Nie dotyczy | Reżyser, producent (nie ma pozycji w napisach) |

---

## 💾 W bazie danych:

```sql
CREATE TABLE movie_person (
    movie_id BIGINT,
    person_id BIGINT,
    role VARCHAR(16),           -- ACTOR, DIRECTOR, WRITER, PRODUCER
    billing_order SMALLINT,     -- 1, 2, 3, ... (tylko dla ACTOR)
    ...
    INDEX (role, billing_order) -- Szybkie sortowanie
);
```

**Użycie:**
- ✅ **Dla ACTOR**: `billing_order` określa pozycję w napisach (1 = główna gwiazda)
- ❌ **Dla DIRECTOR/WRITER/PRODUCER**: `billing_order = NULL` (nie mają pozycji w napisach)

---

## 🔍 W kodzie:

### **1. MovieResource - sortowanie aktorów:**

```php
// Get main actors (top 5 by billing_order)
$actors = $this->people
    ->where('pivot.role', 'ACTOR')
    ->sortBy('pivot.billing_order')  // ← Sortuje po billing_order
    ->take(5)
    ->values();
```

**Efekt:** Aktorzy są wyświetlani w kolejności napisów (1, 2, 3...)

---

### **2. Jobs - tworzenie relacji:**

```php
// Director - brak billing_order
$movie->people()->attach($directorPerson->id, [
    'role' => RoleType::DIRECTOR->value,
    'billing_order' => null,  // ← Director doesn't have billing order
]);

// Actor - z billing_order
$movie->people()->attach($actorPerson->id, [
    'role' => RoleType::ACTOR->value,
    'billing_order' => 1,  // ← Lead actor
]);
```

---

## 📊 Przykład danych:

### **The Matrix (1999):**

```json
{
  "movie_id": 1,
  "people": [
    {
      "person_id": 1,
      "role": "DIRECTOR",
      "billing_order": null
    },
    {
      "person_id": 2,
      "role": "ACTOR",
      "billing_order": 1  // ← Keanu Reeves (lead)
    },
    {
      "person_id": 3,
      "role": "ACTOR",
      "billing_order": 2  // ← Laurence Fishburne
    },
    {
      "person_id": 4,
      "role": "ACTOR",
      "billing_order": 3  // ← Carrie-Anne Moss
    }
  ]
}
```

---

## ⚡ Index w bazie:

```sql
INDEX (role, billing_order)
```

**Co to daje:**
- ✅ Szybkie wyszukiwanie aktorów po roli i pozycji
- ✅ Wydajne sortowanie w zapytaniach
- ✅ Optymalizacja dla `WHERE role = 'ACTOR' ORDER BY billing_order`

---

## 🎯 Podsumowanie:

**`billing_order` to:**
- 📍 Pozycja w napisach końcowych filmu
- 🎭 Tylko dla aktorów (ACTOR role)
- 🔢 Niższa liczba = wyższa pozycja (1 = główna gwiazda)
- ❌ `null` dla reżyserów, producentów, etc.

**Użycie:**
- Sortowanie aktorów w odpowiedziach API
- Wyświetlanie "głównych aktorów" (top 5)
- Zachowanie kolejności z filmu

---

**Ostatnia aktualizacja:** 2025-11-01

