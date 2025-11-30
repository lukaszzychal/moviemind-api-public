# Jak czytać pliki SARIF i wyniki Trivy

> **Data utworzenia:** 2025-11-30  
> **Kontekst:** Przewodnik po czytaniu wyników skanowania bezpieczeństwa Trivy w formacie SARIF  
> **Kategoria:** reference

## 🎯 Cel

Wyjaśnienie jak czytać pliki SARIF i interpretować wyniki skanowania bezpieczeństwa Trivy.

---

## 📋 Metody sprawdzania podatności

### 1. GitHub Security Dashboard (Najłatwiejsze)

**Lokalizacja:** GitHub → Security → Code scanning alerts

**Kroki:**
1. Przejdź do repozytorium na GitHub
2. Kliknij zakładkę **Security**
3. Wybierz **Code scanning alerts**
4. Filtruj według:
   - **Tool:** Trivy
   - **Severity:** CRITICAL, HIGH, MEDIUM, LOW
   - **State:** Open, Closed, Dismissed

**Zalety:**
- ✅ Wizualny interfejs
- ✅ Filtrowanie i sortowanie
- ✅ Historia podatności
- ✅ Automatyczne powiadomienia
- ✅ Linki do plików i linii kodu

---

### 2. Artifacts z GitHub Actions

**Lokalizacja:** GitHub Actions → Run → Artifacts

**Kroki:**
1. Przejdź do **Actions** w repozytorium
2. Wybierz workflow run (np. "Docker Security Scan")
3. Przewiń do sekcji **Artifacts**
4. Pobierz `trivy-scan-report`
5. Rozpakuj i znajdź pliki:
   - `trivy-results.sarif` - wyniki skanowania obrazu Docker
   - `trivy-fs-results.sarif` - wyniki skanowania filesystem

**Zawartość artifactu:**
```
trivy-scan-report/
├── trivy-results.sarif      # Skan obrazu Docker
├── trivy-fs-results.sarif   # Skan filesystem
└── .trivycache/             # Cache Trivy
```

---

### 3. Logi GitHub Actions

**Lokalizacja:** GitHub Actions → Run → Job → Step logs

**Kroki:**
1. Przejdź do **Actions** → wybierz workflow run
2. Kliknij job **Trivy Security Scan**
3. Znajdź step **Run Trivy vulnerability scanner (report)**
4. Sprawdź logi - zawierają tabelę z podatnościami

**Format logów:**
```
📦 alpine:3.22.2
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ Podatność          Pakiet            Wersja            Naprawka          Severity
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ false            CVE-XXXX-XXXXX    package-name      1.2.3              1.2.4              HIGH
```

---

### 4. Lokalne czytanie plików SARIF

**Struktura pliku SARIF:**

Plik SARIF to JSON z następującą strukturą:

```json
{
  "version": "2.1.0",
  "$schema": "https://raw.githubusercontent.com/oasis-tcs/sarif-spec/master/Schemata/sarif-schema-2.1.0.json",
  "runs": [
    {
      "tool": {
        "driver": {
          "name": "Trivy",
          "version": "0.65.0"
        }
      },
      "results": [
        {
          "ruleId": "CVE-2024-XXXXX",
          "message": {
            "text": "Vulnerability description"
          },
          "level": "error",
          "locations": [
            {
              "physicalLocation": {
                "artifactLocation": {
                  "uri": "package-name"
                },
                "region": {
                  "startLine": 1
                }
              }
            }
          ],
          "properties": {
            "security-severity": "8.5",
            "precision": "very-high"
          }
        }
      ]
    }
  ]
}
```

**Kluczowe pola:**
- `ruleId` - ID podatności (np. CVE-2024-XXXXX)
- `message.text` - Opis podatności
- `level` - Poziom: `error` (CRITICAL/HIGH), `warning` (MEDIUM), `note` (LOW)
- `locations[].physicalLocation.artifactLocation.uri` - Pakiet/plik z podatnością
- `properties.security-severity` - CVSS score (0-10)

**Narzędzia do czytania SARIF:**

1. **VS Code Extension:**
   - Zainstaluj "SARIF Viewer" extension
   - Otwórz plik `.sarif`
   - Zobacz wyniki w panelu Problems

2. **Online Viewer:**
   - [SARIF Web Viewer](https://microsoft.github.io/sarif-web-component/)
   - Przeciągnij plik SARIF
   - Zobacz wyniki w przeglądarce

3. **jq (command line):**
   ```bash
   # Liczba podatności
   jq '.runs[0].results | length' trivy-results.sarif
   
   # Lista wszystkich CVE
   jq -r '.runs[0].results[].ruleId' trivy-results.sarif | sort | uniq
   
   # Podatności CRITICAL/HIGH
   jq '.runs[0].results[] | select(.level == "error")' trivy-results.sarif
   
   # Podatności z CVSS > 7.0
   jq '.runs[0].results[] | select(.properties."security-severity" > 7.0)' trivy-results.sarif
   ```

---

## 🔍 Interpretacja wyników Trivy

### Poziomy podatności (Severity)

| Poziom | Opis | CVSS Score | Działanie |
|--------|------|-----------|-----------|
| **CRITICAL** | Krytyczne podatności | 9.0-10.0 | Natychmiastowa naprawa |
| **HIGH** | Wysokie ryzyko | 7.0-8.9 | Priorytetowa naprawa |
| **MEDIUM** | Średnie ryzyko | 4.0-6.9 | Planowana naprawa |
| **LOW** | Niskie ryzyko | 0.1-3.9 | Opcjonalna naprawa |

### Typy skanowania

1. **Image Scan** (`trivy-results.sarif`):
   - Skanuje obraz Docker
   - Wykrywa podatności w:
     - Systemie operacyjnym (Alpine, Ubuntu, etc.)
     - Zainstalowanych pakietach systemowych
     - Zależnościach aplikacji (Composer, npm, etc.)

2. **Filesystem Scan** (`trivy-fs-results.sarif`):
   - Skanuje pliki w repozytorium
   - Wykrywa podatności w:
     - Plikach konfiguracyjnych
     - Zależnościach (composer.json, package.json)
     - Kodzie źródłowym

---

## 📊 Przykładowe komendy do analizy

### Użycie skryptu analizy (Zalecane)

**Najłatwiejszy sposób** - użyj gotowego skryptu:

```bash
# 1. Pobierz artifact z GitHub Actions
gh run download <run-id> -n trivy-scan-report

# 2. Rozpakuj artifact
unzip trivy-scan-report.zip

# 3. Uruchom skrypt analizy
./scripts/analyze-trivy-sarif.sh trivy-results.sarif
```

**Co pokazuje skrypt:**
- 📈 Statystyki (wszystkie podatności, CRITICAL/HIGH, MEDIUM, LOW)
- 🔍 Lista CVE (CRITICAL/HIGH)
- 📋 Szczegóły podatności (CVE, opis, pakiet)

**Przykładowy output:**
```
📊 Analiza pliku SARIF: trivy-results.sarif

📈 Statystyki:
   Wszystkie podatności: 15
   CRITICAL/HIGH: 3
   MEDIUM: 8
   LOW: 4

🔍 Lista CVE (CRITICAL/HIGH):
CVE-2024-XXXXX
CVE-2024-YYYYY
CVE-2024-ZZZZZ

📋 Szczegóły podatności CRITICAL/HIGH:
CVE-2024-XXXXX | Vulnerability description | package-name
```

### Sprawdzenie liczby podatności (ręcznie z jq)

```bash
# Pobierz artifact z GitHub Actions
gh run download <run-id> -n trivy-scan-report

# Rozpakuj
unzip trivy-scan-report.zip

# Liczba wszystkich podatności
jq '.runs[0].results | length' trivy-results.sarif

# Liczba CRITICAL/HIGH
jq '[.runs[0].results[] | select(.level == "error")] | length' trivy-results.sarif
```

### Lista wszystkich CVE

```bash
# Wszystkie CVE
jq -r '.runs[0].results[].ruleId' trivy-results.sarif | sort | uniq

# Tylko CRITICAL/HIGH
jq -r '.runs[0].results[] | select(.level == "error") | .ruleId' trivy-results.sarif | sort | uniq
```

### Szczegóły podatności

```bash
# Wszystkie podatności z opisem
jq '.runs[0].results[] | {cve: .ruleId, severity: .level, description: .message.text, package: .locations[0].physicalLocation.artifactLocation.uri}' trivy-results.sarif

# Podatności z CVSS > 8.0
jq '.runs[0].results[] | select(.properties."security-severity" > 8.0) | {cve: .ruleId, cvss: .properties."security-severity", description: .message.text}' trivy-results.sarif
```

---

## 🛠️ Naprawa podatności

### 1. Zaktualizuj pakiety systemowe

```dockerfile
# Przed
FROM alpine:3.22.2

# Po (zaktualizuj do najnowszej wersji)
FROM alpine:3.22.3
RUN apk update && apk upgrade
```

### 2. Zaktualizuj zależności Composer

```bash
cd api
composer update --with-all-dependencies
composer audit  # Sprawdź podatności
```

### 3. Zaktualizuj zależności npm (jeśli używane)

```bash
npm audit
npm audit fix
```

### 4. Sprawdź czy podatność dotyczy Twojego użycia

Niektóre podatności mogą nie dotyczyć Twojego przypadku użycia:
- Podatność w nieużywanym komponencie
- Podatność wymagająca specyficznej konfiguracji
- Podatność w funkcji, której nie używasz

**Zawsze sprawdź:**
- [CVE Details](https://www.cvedetails.com/) - szczegóły podatności
- [NVD](https://nvd.nist.gov/) - National Vulnerability Database
- Dokumentacja pakietu - czy jest dostępna aktualizacja

---

## 📌 Najlepsze praktyki

1. **Regularne skanowanie:**
   - Codziennie (automatycznie przez GitHub Actions)
   - Przed każdym release'em
   - Po aktualizacji zależności

2. **Priorytetyzacja:**
   - Najpierw CRITICAL i HIGH
   - Potem MEDIUM (jeśli dotyczy używanych funkcji)
   - LOW można zignorować (jeśli nie dotyczy)

3. **Dokumentacja:**
   - Dokumentuj decyzje o nie naprawianiu podatności
   - Uzasadnij dlaczego podatność nie dotyczy Twojego przypadku

4. **Monitoring:**
   - Sprawdzaj GitHub Security Dashboard regularnie
   - Włącz powiadomienia dla nowych podatności
   - Śledź status naprawy

---

## 🔗 Powiązane dokumenty

- [GitHub Security Documentation](https://docs.github.com/en/code-security)
- [Trivy Documentation](https://aquasecurity.github.io/trivy/)
- [SARIF Specification](https://docs.oasis-open.org/sarif/sarif/v2.1.0/sarif-v2.1.0.html)
- [CVE Details](https://www.cvedetails.com/)

---

**Ostatnia aktualizacja:** 2025-11-30

