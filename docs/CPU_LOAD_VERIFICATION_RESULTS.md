# Wyniki Weryfikacji CPU Load w Docker

> **Data:** 2025-12-19  
> **Środowisko:** Docker Desktop na macOS  
> **Kontener:** moviemind-php (Alpine Linux)

---

## 📋 Wyniki Testów

### ✅ Test 1: Dostępność `sys_getloadavg()`

**Wynik:** ✅ **PASS**

```bash
docker compose exec -T php php -r "var_dump(function_exists('sys_getloadavg'));"
# bool(true)
```

**Wartości:**
```php
array(3) {
  [0]=> float(0)    // 1-minute load
  [1]=> float(0)    // 5-minute load
  [2]=> float(0)    // 15-minute load
}
```

**Wnioski:**
- ✅ Funkcja jest dostępna w kontenerze
- ✅ Zwraca poprawną strukturę (tablica 3 wartości float)
- ⚠️ Wartości są 0 (system bez obciążenia lub kontener pokazuje własne obciążenie)

---

### ✅ Test 2: Porównanie Load Hosta vs Kontenera

**Wynik:** ✅ **PASS** (wartości różnią się - działa poprawnie)

**Przed obciążeniem:**
```
HOST load:     3.34 3.89 4.04
KONTAINER load: 0.00 0.00 0.00
Różnica: 3.34
```

**Wnioski:**
- ✅ **Wartości są RÓŻNE** → `sys_getloadavg()` pokazuje load kontenera, nie hosta
- ✅ Kontener pokazuje niskie obciążenie (0.00) podczas gdy host ma wysokie (3.34)
- ✅ **To oznacza, że funkcja działa poprawnie w kontenerze!**

**Uwaga:** Wysokie obciążenie hosta (3.34) jest normalne dla macOS z wieloma uruchomionymi aplikacjami. Kontener pokazuje własne, niskie obciążenie.

---

### ✅ Test 3: Generowanie Obciążenia i Weryfikacja

**Wynik:** ✅ **PASS** (kontener reaguje na własne obciążenie)

**Przed obciążeniem:**
```
Host 1-min load:      3.34
Kontener 1-min load:  0.00
Różnica:              3.34
```

**Po obciążeniu (10 sekund):**
```
Host 1-min load:      3.80  (wzrósł o +0.46)
Kontener 1-min load:  0.08  (wzrósł z 0.00)
Różnica:              3.72
```

**Wnioski:**
- ✅ **Kontener wykrył własne obciążenie** - load wzrósł z 0.00 do 0.08
- ✅ Host też wzrósł (3.34 → 3.80), ale to może być z powodu innych procesów
- ✅ **Różnica między hostem a kontenerem pozostała duża (3.72)** → kontener pokazuje własne obciążenie
- ✅ **sys_getloadavg() działa poprawnie w kontenerze Docker!**

**Interpretacja:**
- Kontener: 0.00 → 0.08 (wzrost o 0.08) = wykrył obciążenie w kontenerze
- Host: 3.34 → 3.80 (wzrost o 0.46) = może być z powodu kontenera + innych procesów
- **Kluczowe:** Kontener pokazuje własne obciążenie (nie jest identyczne z hostem)

---

### ✅ Test 4: Testy Jednostkowe (PHPUnit)

**Wynik:** ✅ **PASS** (wszystkie testy przeszły)

```
PASS  Tests\Feature\CpuLoadVerificationTest
✓ cpu load function exists
✓ cpu load returns array
✓ cpu load values reasonable
✓ cpu load consistency

Tests:    4 passed (14 assertions)
Duration: 0.37s
```

**Wnioski:**
- ✅ Wszystkie testy przeszły
- ✅ Funkcja działa poprawnie
- ✅ Wartości są rozsądne i spójne

---

### ✅ Test 5: Cgroup Metrics

**Wynik:** ✅ **PASS** (cgroup v2 dostępne)

```
cgroup v2: TAK
usage_usec 5734243
user_usec 5649121
system_usec 85122
```

**Wnioski:**
- ✅ Cgroup v2 jest dostępne w kontenerze
- ✅ Można użyć jako alternatywy dla `sys_getloadavg()` (zaawansowane)
- ⚠️ Wymaga bardziej złożonej implementacji (porównanie z poprzednimi wartościami)

---

## 🎯 Decyzja Finalna

### ✅ **REKOMENDACJA: UŻYJ CPU LOAD (40% wagi)**

**Uzasadnienie:**
1. ✅ **Test 1 PASS** - `sys_getloadavg()` jest dostępne
2. ✅ **Test 2 PASS** - Wartości różnią się (kontener pokazuje własne obciążenie, nie hosta)
3. ✅ **Test 3 PASS** - Kontener reaguje na własne obciążenie (0.00 → 0.08)
4. ✅ **Test 4 PASS** - Wszystkie testy jednostkowe przeszły
5. ✅ **Test 5 PASS** - Cgroup v2 dostępne (opcjonalna alternatywa)

**Wzór Load Factor:**
```php
// Pełna implementacja z CPU
$loadFactor = ($cpuLoad * 0.4) + ($queueRatio * 0.4) + ($activeJobsRatio * 0.2);
```

**Uwagi:**
- Kontener pokazuje własne obciążenie (nie hosta) ✅
- Wartości są niskie (0.00-0.08) bo kontener jest lekki ✅
- Load wzrósł po obciążeniu (0.00 → 0.08) - funkcja działa ✅
- Różnica z hostem jest duża (3.34 vs 0.00) - kontener nie pokazuje hosta ✅

---

## 📊 Podsumowanie Checklisty

- [x] **Test 1:** Sprawdź czy `sys_getloadavg()` jest dostępne → ✅ **PASS**
- [x] **Test 2:** Porównaj load hosta vs kontenera → ✅ **PASS** (różne wartości)
- [x] **Test 3:** Generuj obciążenie i sprawdź czy load się zmienia → ✅ **PASS** (kontener reaguje)
- [x] **Test 4:** Uruchom testy jednostkowe → ✅ **PASS** (4 testy, 14 assertions)
- [x] **Test 5:** Sprawdź cgroup → ✅ **PASS** (cgroup v2 dostępne)

**Decyzja:** ✅ **Użyj CPU load (40% wagi) w adaptive rate limiting**

---

## 🔧 Implementacja

### Wzór Load Factor:
```php
$loadFactor = ($cpuLoad * 0.4) + ($queueRatio * 0.4) + ($activeJobsRatio * 0.2);
```

### Normalizacja CPU Load:
```php
$cpuCores = (int) config('rate-limiting.cpu.cores', 4);
$normalizedCpuLoad = min(1.0, $load1min / $cpuCores);
```

**Przykład:**
- Load kontenera: 0.08
- CPU cores: 4
- Normalized: 0.08 / 4 = 0.02 (2% obciążenia)

---

**Data weryfikacji:** 2025-12-19  
**Weryfikował:** Automated tests + manual verification  
**Status:** ✅ **GOTOWE DO IMPLEMENTACJI**

