# 📋 Szablon Zadania

Użyj tego szablonu do tworzenia nowych zadań/issues.

---

## 📝 **Szablon**

```markdown
#### `TASK-XXX` - Tytuł Zadania
- **Status:** ⏳ PENDING
- **Priorytet:** 🔴 Wysoki / 🟡 Średni / 🟢 Niski
- **Szacowany czas:** X godzin (opcjonalnie)
- **Opis:** Krótki opis zadania (1-2 zdania)
- **Szczegóły:** [link do szczegółowego opisu](./PLIK.md) lub bezpośredni opis tutaj
- **Zależności:** TASK-XXX, TASK-YYY (jeśli wymagane)
- **Utworzone:** YYYY-MM-DD
- **Zakończone:** YYYY-MM-DD (wypełnij po zakończeniu)

**Podzadania (jeśli potrzebne):**
- [ ] Podzadanie 1
- [ ] Podzadanie 2
```

---

## 🎯 **Statusy**

- `⏳ PENDING` - Zadanie oczekuje na wykonanie
- `🔄 IN_PROGRESS` - Zadanie w trakcie wykonywania
- `✅ COMPLETED` - Zadanie zakończone
- `❌ CANCELLED` - Zadanie anulowane

---

## 🔴 **Priorytety**

- `🔴 Wysoki` - Krytyczne, należy wykonać jak najszybciej
- `🟡 Średni` - Ważne, ale nie krytyczne
- `🟢 Niski` - Można wykonać później

---

## 📝 **Przykłady**

### **Przykład 1: Proste zadanie bez szczegółowego opisu**

```markdown
#### `TASK-002` - Dodanie Rate Limiting do API
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 2 godziny
- **Opis:** Implementacja rate limiting dla endpointów API używając Laravel Throttle middleware
- **Szczegóły:** Dodać middleware do routes/api.php, skonfigurować limity w config/throttle.php
- **Zależności:** Brak
- **Utworzone:** 2025-01-27
```

### **Przykład 2: Złożone zadanie ze szczegółowym opisem**

```markdown
#### `TASK-003` - Implementacja Caching Layer
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 4-6 godzin
- **Opis:** Dodanie warstwy cache dla często używanych danych (movies, people)
- **Szczegóły:** [docs/issue/CACHING_IMPLEMENTATION.md](./CACHING_IMPLEMENTATION.md)
- **Zależności:** Brak
- **Utworzone:** 2025-01-27

**Podzadania:**
- [ ] Utworzenie CacheService
- [ ] Dodanie cache tags dla movies i people
- [ ] Implementacja cache invalidation
- [ ] Testy dla cache layer
```

---

## 💡 **Wskazówki**

1. **Tytuł zadania** - Powinien być krótki i opisowy (max 60 znaków)
2. **Opis** - 1-2 zdania, wystarczające aby zrozumieć cel zadania
3. **Szczegóły** - Jeśli zadanie jest złożone, stwórz osobny plik z dokładnym opisem
4. **Zależności** - Wymień wszystkie zadania, które muszą być wykonane przed tym zadaniem
5. **Podzadania** - Używaj dla złożonych zadań, które można podzielić na mniejsze kroki

---

**Ostatnia aktualizacja:** 2025-01-27

