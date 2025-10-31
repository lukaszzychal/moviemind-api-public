# CI: Docker-in-Docker vs. Native PHP

## 📊 Porównanie Podejść

### Obecne Rozwiązanie: Native PHP w GitHub Actions

**Jak działa:**
- Używa `shivammathur/setup-php@v2` do instalacji PHP
- Instaluje rozszerzenia bezpośrednio w runnerze
- Testy używają SQLite in-memory
- Composer cache dla szybszych buildów

**Zalety:**
- ✅ **Szybkość** - brak budowania obrazów (~30-40s vs ~2-3min)
- ✅ **Prostota** - mniej kroków, łatwiejsze debugowanie
- ✅ **Zasoby** - mniejsze zużycie pamięci i CPU
- ✅ **Cache** - łatwe cache Composer packages
- ✅ **Izolacja** - każde uruchomienie w świeżym środowisku
- ✅ **Debugowanie** - łatwe logi, bezpośredni dostęp do środowiska

**Wady:**
- ❌ **Różnice środowiska** - różne od produkcji (SQLite vs PostgreSQL)
- ❌ **Zależności** - trzeba ręcznie instalować rozszerzenia PHP
- ❌ **Wersje** - możliwe różnice w wersjach PHP/extensions
- ❌ **Kompatybilność** - niektóre funkcje mogą działać inaczej

---

### Alternatywa: Docker-in-Docker (DinD)

**Jak działa:**
- Buduje obraz Docker z aplikacją
- Uruchamia kontener z testami
- Używa docker-compose dla bazy danych (PostgreSQL)
- Pełna zgodność z środowiskiem produkcyjnym

**Zalety:**
- ✅ **Zgodność** - identyczne środowisko jak produkcja
- ✅ **Izolacja** - pełna izolacja w kontenerze
- ✅ **Reprodukcja** - łatwe odtworzenie lokalnie
- ✅ **Zależności** - wszystko w Dockerfile
- ✅ **PostgreSQL** - możliwość testów z prawdziwą bazą
- ✅ **Spójność** - ten sam obraz dla dev/test/prod

**Wady:**
- ❌ **Wolność** - budowanie obrazu ~2-3 minuty
- ❌ **Złożoność** - więcej kroków, więcej do debugowania
- ❌ **Zasoby** - większe zużycie (Docker daemon + kontenery)
- ❌ **Cache** - trzeba cache'ować warstwy obrazu
- ❌ **Docker-in-Docker** - możliwe problemy z uprawnieniami

---

## 🎯 Rekomendacja dla Twojego Projektu

### Obecne podejście (Native) jest lepsze gdy:

1. **Szybkość feedbacku** - chcesz szybkie wyniki (< 1 minuta)
2. **Proste testy** - testy jednostkowe/feature nie wymagają PostgreSQL
3. **CI/CD prosty** - mniej kroków = mniej problemów
4. **Zasoby** - ograniczone w GitHub Actions (free tier)

### Docker byłby lepszy gdy:

1. **Testy integracyjne** - potrzebujesz PostgreSQL, Redis razem
2. **Produkcja = Testy** - dokładna zgodność środowiska
3. **Kompleksowe testy** - wiele serwisów, queue workers
4. **Team consistency** - cały team używa Docker lokalnie

---

## 💡 Hybrydowe Podejście (Najlepsze z obu)

Możesz używać **obu podejść równolegle**:

```yaml
jobs:
  test-fast:
    # Native PHP - szybkie testy jednostkowe
    runs-on: ubuntu-latest
    # ... obecne rozwiązanie ...
  
  test-integration:
    # Docker - testy integracyjne z PostgreSQL
    runs-on: ubuntu-latest
    # ... docker-compose z pełnym stackiem ...
```

**Korzyści:**
- Szybkie feedback dla prostych testów (native)
- Pełne testy integracyjne w Docker (rzadziej, np. tylko na main)

---

## 🔧 Kiedy Przejść na Docker?

Rozważ Docker gdy:

1. ✅ Testy zaczynają failować przez różnice SQLite vs PostgreSQL
2. ✅ Potrzebujesz testować z Redis/Queue/Horizon
3. ✅ Zespół ma problemy z "works on my machine"
4. ✅ Budujesz już Docker images (masz `docker-build` job)
5. ✅ Chcesz testować dokładnie to, co w produkcji

---

## 📈 Porównanie Czasów

| Operacja | Native PHP | Docker |
|----------|-----------|--------|
| Setup PHP | ~10s | - |
| Build image | - | ~2-3min |
| Install deps | ~20s (z cache) | ~30s (w kontenerze) |
| Run tests | ~10s | ~15s |
| **Total** | **~40s** | **~3-4min** |

---

## 🎬 Implementacja Docker CI (Przykład)

Zobacz `.github/workflows/ci-docker.example.yml` dla pełnego przykładu.

**Kluczowe różnice:**
- Używa `docker/setup-buildx-action` zamiast `setup-php`
- Buduje obraz przed testami
- Używa `docker-compose` dla bazy danych
- Testy w kontenerze z `composer test`

---

## ✅ Obecne Rozwiązanie jest OK jeśli:

- Testy przechodzą stabilnie
- SQLite in-memory wystarcza dla testów
- Szybkość CI jest ważna
- Nie masz problemów z "works locally but fails in CI"

## 🔄 Rozważ Docker gdy:

- Występują problemy z różnicami środowisk
- Potrzebujesz testów z PostgreSQL
- Chcesz pełną zgodność z produkcją
- Masz więcej czasu na CI (budowanie obrazów)

