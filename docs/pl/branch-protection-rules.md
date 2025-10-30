# Zasady Ochrony Gałęzi dla MovieMind API

## Przegląd
Ten dokument opisuje zalecane zasady ochrony gałęzi dla repozytorium MovieMind API, aby zapewnić jakość kodu, bezpieczeństwo i łatwość utrzymania.

## Zasady Ochrony Głównej Gałęzi

### Wymagane Sprawdzenia Statusu
- ✅ Wymagaj przejścia sprawdzeń statusu przed scaleniem
  - `gitleaks-security-scan` — skan bezpieczeństwa GitLeaks
  - `security-audit` — audyt bezpieczeństwa zależności (Composer)
  - `phpunit-tests` — testy jednostkowe PHP (gdy zaimplementowane)
  - `code-quality` — sprawdzenia jakości kodu (gdy zaimplementowane)

### Ustawienia Ochrony Gałęzi
- ✅ Wymagaj aktualności gałęzi przed scaleniem
- ✅ Wymagaj recenzji pull request przed scaleniem
  - Wymagani recenzenci: 1
  - Odrzuć przestarzałe recenzje przy nowych commitach
  - Wymagaj recenzji od właścicieli kodu (CODEOWNERS)
- ✅ Ogranicz pushy tworzące pliki większe niż 100MB
- ✅ Wymagaj liniowej historii (bez commitów merge)
- ✅ Uwzględnij administratorów w zasadach ochrony

### Dodatkowe Ustawienia Bezpieczeństwa
- ✅ Wymagaj podpisanych commitów (zalecane)
- ✅ Blokuj force pushy
- ✅ Ogranicz tworzenie
- ✅ Ogranicz aktualizacje
- ✅ Ogranicz usuwanie
- ✅ Wymagaj udanych wdrożeń
- ✅ Wymagaj wyników skanowania kodu
- ✅ Automatycznie żądaj recenzji kodu Copilot

## Konwencje Nazewnictwa Gałęzi

### Chronione Gałęzie
- `main` — kod gotowy do produkcji
- `develop` — gałąź integracyjna dla funkcji
- `release/*` — gałęzie przygotowania wydań

### Gałęzie Funkcji
- `feature/feature-name` — nowe funkcje
- `bugfix/bug-description` — naprawy błędów
- `hotfix/critical-fix` — krytyczne naprawy produkcyjne
- `chore/task-description` — zadania konserwacyjne

## Wymagania Recenzji Kodu

### Recenzenci
- Wymagany: co najmniej 1 recenzent
- Właściciele Kodu — recenzja wymagana dla zmian w:
  - `.github/workflows/` — przepływy CI/CD
  - `docker-compose.yml` — zmiany infrastruktury
  - `composer.json` — zmiany zależności
  - `README.md` — zmiany dokumentacji

### Wytyczne Recenzji
1. Bezpieczeństwo — zmiany security wymagają recenzji zespołu bezpieczeństwa
2. Zależności — aktualizacje wymagają dokładnej recenzji
3. Infrastruktura — Docker i wdrożenia wymagają recenzji infrastruktury
4. Dokumentacja — zmiany README i API docs wymagają recenzji dokumentacji

## Automatyczne Sprawdzenia

### Sprawdzenia Przed Scaleniem
1. Skan GitLeaks — wykrywa sekrety i wrażliwe informacje
2. Audyt Bezpieczeństwa — znane luki w zależnościach
3. Jakość Kodu — spełnienie standardów jakości
4. Testy — wszystkie testy muszą przejść

### Akcje Po Scaleniu
1. Dependabot — automatyczne aktualizacje zależności
2. Skanowanie Bezpieczeństwa — ciągłe monitorowanie
3. Dokumentacja — automatyczna aktualizacja dokumentacji API

## Procedury Awaryjne

### Proces Hotfix
1. Utwórz gałąź `hotfix/critical-issue` z `main`
2. Zaimplementuj minimalną naprawę
3. Poproś o przyspieszoną recenzję
4. Scal bezpośrednio do `main` (omijając normalny proces)
5. Wykonaj cherry-pick do `develop`

### Reakcja na Incydent Bezpieczeństwa
1. Natychmiast zablokuj dotknięte gałęzie
2. Utwórz poradę bezpieczeństwa (security advisory)
3. Zaimplementuj naprawę w prywatnej gałęzi
4. Skoordynuj wydanie z zespołem bezpieczeństwa

## Kroki Implementacji

### Ustawienia Repozytorium GitHub
1. Przejdź do Settings → Branches
2. Kliknij Add rule dla gałęzi `main`
3. Skonfiguruj następujące ustawienia:

```yaml
Zasada Ochrony Gałęzi dla 'main':
  Wymagaj pull request przed scaleniem:
    ✅ Wymagane
    ✅ Wymagaj zatwierdzeń: 1
    ✅ Odrzuć przestarzałe zatwierdzenia PR przy nowych commitach
    ✅ Wymagaj recenzji od właścicieli kodu

  Wymagaj przejścia sprawdzeń statusu:
    ✅ Wymagane
    ✅ Wymagaj aktualności gałęzi przed scaleniem
    ✅ Sprawdzenia statusu: gitleaks-security-scan, security-audit

  Wymagaj podpisanych commitów:
    ✅ Wymagane

  Wymagaj liniowej historii:
    ✅ Wymagane

  Blokuj force pushy:
    ✅ Wymagane

  Ogranicz tworzenie / aktualizacje / usuwanie:
    ✅ Wymagane

  Wymagaj udanych wdrożeń:
    ✅ Wymagane
    ✅ Środowiska: production, staging

  Wymagaj wyników skanowania kodu:
    ✅ Wymagane
    ✅ Narzędzia: CodeQL, Semgrep

  Automatycznie żądaj recenzji Copilot:
    ✅ Wymagane

  Uwzględnij administratorów:
    ✅ Wymagane
```

### Plik Właścicieli Kodu (CODEOWNERS)
Utwórz `.github/CODEOWNERS`:

```
# Globalni właściciele
* @lukaszzychal

# Bezpieczeństwo i CI/CD
/.github/ @lukaszzychal
/.gitleaks.toml @lukaszzychal

# Infrastruktura
/docker-compose.yml @lukaszzychal
/Dockerfile @lukaszzychal

# Zależności
/composer.json @lukaszzychal
/composer.lock @lukaszzychal

# Dokumentacja
/README.md @lukaszzychal
/docs/ @lukaszzychal
```

## Poziomy Bezpieczeństwa i Rekomendacje

### Podstawowa Ochrona (MVP)
- ✅ PR przed scaleniem, status checks, blokada force push, liniowa historia

### Rozszerzona Ochrona (Produkcja)
- ✅ Wszystko z MVP + podpisane commity, restrykcje operacji, wdrożenia, skanowanie kodu

### Ochrona Enterprise (Wysokie Bezpieczeństwo)
- ✅ Wszystko z Produkcji + recenzje Copilot, wielu recenzentów, ścisłe uprawnienia bypass

## Monitorowanie i Alerty

### Alerty Bezpieczeństwa
- Dependabot — automatyczne powiadomienia o lukach
- Secret Scanning — wykrywanie sekretów w czasie rzeczywistym
- GitLeaks — zaplanowane skany bezpieczeństwa

### Metryki Jakości
- Pokrycie Kodu — minimum 80% testami
- Ocena Bezpieczeństwa — utrzymuj A+
- Zdrowie Zależności — aktualne zależności

## Zgodność i Audyt

### Ślad Audytowy
- Wszystkie zmiany przez pull requesty
- Logowanie i archiwizacja skanów bezpieczeństwa
- Utrzymywana historia recenzji kodu

### Wymagania Zgodności
- GDPR — bezpieczne przetwarzanie danych
- Bezpieczeństwo — regularne oceny bezpieczeństwa
- Jakość — wysokie standardy jakości kodu

---

Uwaga: Zasady wdrażaj stopniowo i dostosowuj do wielkości zespołu oraz wymagań projektu. Zacznij od podstawowej ochrony i dodawaj kolejne zasady wraz z dojrzewaniem projektu.
