# Analiza legalności i możliwości komercyjnego wykorzystania API TMDB i TVmaze

## 📋 Podsumowanie wykonawcze

### TMDB (The Movie Database)
- ❌ **Komercyjne wykorzystanie wymaga pisemnej umowy** - nawet aplikacje z reklamami
- 💰 **Koszty:** ~$149/miesiąc (małe aplikacje) do $42,000/rok (duże projekty)
- ✅ **Niekomercyjne:** darmowe z wymaganą atrybucją

### TVmaze
- ✅ **Komercyjne wykorzystanie dozwolone** - licencja CC BY-SA
- 💰 **Koszty:** darmowe (publiczne API)
- ⚠️ **Wymagania:** atrybucja + ShareAlike (jeśli redystrybuujesz dane)

---

## 🎬 TMDB (The Movie Database) - Szczegółowa analiza

### Warunki użytkowania

#### ✅ Co jest dozwolone (niekomercyjnie)

1. **Darmowe API** dla projektów niekomercyjnych
2. **Wymagana atrybucja:**
   - Logo TMDB (mniej prominentne niż własne logo)
   - Tekst: *"This [aplikacja] uses TMDB and the TMDB APIs but is not endorsed, certified, or otherwise approved by TMDB."*
   - Link do https://www.themoviedb.org

3. **Ograniczenia cache:** maksymalnie 6 miesięcy

#### ❌ Co jest zabronione (bez licencji komercyjnej)

Zgodnie z [API Terms of Use TMDB](https://www.themoviedb.org/api-terms-of-use):

1. **Wszelkie wykorzystanie komercyjne:**
   - Aplikacje z reklamami (nawet darmowe)
   - Aplikacje płatne/subskrypcyjne
   - Serwisy generujące przychód (nawet pośrednio)
   - "Destination websites" (wyszukiwarki, chatboty)
   - Trening modeli AI/ML używających danych TMDB

2. **Zabronione działania:**
   - Sprzedaż, leasing, sublicencjonowanie API lub danych
   - Używanie TMDB jako hostingu obrazów dla reklam
   - Tworzenie pochodnych danych TMDB
   - Używanie w chatbotach/LLM bez licencji

### Licencja komercyjna TMDB

#### Proces uzyskania

1. **Kontakt:** sales@themoviedb.org
2. **Negocjacja:** indywidualna, zależna od:
   - Wolumenu zapytań API
   - Modelu monetyzacji
   - Funkcjonalności aplikacji
   - Geografii użytkowników

#### Szacunkowe koszty (na podstawie raportów użytkowników)

| Typ aplikacji | Szacunkowy koszt | Źródło |
|---------------|-----------------|--------|
| Mała aplikacja (indie) | ~$149/miesiąc | [Reddit reports](https://www.reddit.com/r/iOSProgramming/comments/1jpke10/) |
| Duża aplikacja/enterprise | ~$42,000/rok | [Reddit reports](https://www.reddit.com/r/iOSProgramming/comments/1jpke10/) |
| Negocjowane indywidualnie | Zależne od użycia | Oficjalna polityka TMDB |

**Uwaga:** TMDB nie publikuje oficjalnego cennika - ceny są negocjowane indywidualnie.

### Wymagania atrybucji TMDB

Nawet z licencją komercyjną musisz:

1. **Wyświetlać logo TMDB:**
   - Używać tylko zatwierdzonych wersji logo
   - Logo mniej prominentne niż własne logo
   - Nie modyfikować (kolory, rotacja, itp.)

2. **Tekst atrybucji:**
   > "This [website, program, service, application, product] uses TMDB and the TMDB APIs but is not endorsed, certified, or otherwise approved by TMDB."

3. **Link:** https://www.themoviedb.org

### Uwaga o RapidAPI (historyczne)

⚠️ **Uwaga:** RapidAPI zostało usunięte z projektu w ramach migracji do portfolio. Informacje poniżej są historyczne.

- RapidAPI to była platforma dostępu (już nieużywana)
- Warunki użytkowania TMDB nadal obowiązują niezależnie od platformy
- Dla produkcji wymagana jest licencja komercyjna TMDB

---

## 📺 TVmaze - Szczegółowa analiza

### Warunki użytkowania

#### ✅ Co jest dozwolone (w tym komercyjnie)

1. **Licencja:** [Creative Commons Attribution-ShareAlike (CC BY-SA)](https://creativecommons.org/licenses/by-sa/4.0/)
2. **Komercyjne wykorzystanie:** ✅ **DOZWOLONE**
3. **Koszty:** ✅ **DARMOWE** (publiczne API)

#### Wymagania licencji CC BY-SA

1. **Atrybucja (wymagana):**
   - Musisz podać TVmaze jako źródło danych
   - Link do TVmaze w aplikacji/stronie
   - Możesz użyć URL z API do linkowania

2. **ShareAlike (jeśli redystrybuujesz):**
   - Jeśli udostępniasz dane lub ich pochodne, musisz użyć tej samej licencji (CC BY-SA)
   - Dotyczy tylko jeśli **redystrybuujesz** dane (np. eksportujesz do pliku, udostępniasz API)

#### Ograniczenia techniczne

1. **Rate limiting:**
   - Minimum **20 zapytań na 10 sekund** na IP
   - HTTP 429 przy przekroczeniu
   - Zalecane: retry z opóźnieniem

2. **Cache:**
   - ✅ Dozwolony (nawet długoterminowy)
   - Obrazy mogą być cache'owane w nieskończoność (URL się nie zmienia)

3. **CORS:** ✅ Włączony (można używać bezpośrednio w aplikacjach web)

### Enterprise API TVmaze

Jeśli potrzebujesz:
- Wyższych limitów zapytań
- SLA (Service Level Agreement)
- Dodatkowych funkcji
- Innej licencji (bez ShareAlike)

**Kontakt:** sales department TVmaze

### Premium Membership TVmaze

- **User API:** dostęp do danych użytkownika (followed shows, watched episodes, votes)
- **Koszt:** wymaga Premium membership
- **Funkcjonalność:** read-write access do konta użytkownika

---

## 💰 Porównanie: Monetyzacja przez reklamy (historyczne - RapidAPI usunięte)

**Uwaga:** Sekcja poniżej zawiera analizę scenariuszy z RapidAPI, które zostało usunięte z projektu w ramach migracji do portfolio. Informacje są zachowane dla kontekstu historycznego.

### Scenariusz 1: Serwis z reklamami używający TMDB

| Aspekt | Status | Koszt |
|--------|--------|-------|
| **Legalność** | ⚠️ Wymaga licencji komercyjnej | ~$149-3500/miesiąc |
| **Atrybucja** | ✅ Wymagana (logo + tekst) | - |
| **Platforma API** | ⚠️ Nie zwalnia z licencji | + koszty platformy (jeśli używana) |
| **Ryzyko** | 🔴 Wysokie (bez licencji = naruszenie) | - |

**Wniosek:** ❌ **NIE MA SENSU** bez licencji komercyjnej TMDB

### Scenariusz 2: Serwis z reklamami używający TVmaze

| Aspekt | Status | Koszt |
|--------|--------|-------|
| **Legalność** | ✅ Dozwolone (CC BY-SA) | Darmowe |
| **Atrybucja** | ✅ Wymagana (link do TVmaze) | - |
| **ShareAlike** | ⚠️ Tylko jeśli redystrybuujesz | - |
| **Rate limits** | ⚠️ 20 req/10s (może być za mało) | - |
| **Ryzyko** | 🟢 Niskie (zgodne z licencją) | - |

**Wniosek:** ✅ **MA SENSU** - legalne i darmowe

### Scenariusz 3: Serwis na platformie API używający TMDB (historyczne)

| Aspekt | Status | Koszt |
|--------|--------|-------|
| **Legalność** | ⚠️ Wymaga licencji komercyjnej TMDB | ~$149-3500/miesiąc |
| **Platforma API** | ⚠️ Dodatkowe koszty platformy | + koszty platformy |
| **Atrybucja** | ✅ Wymagana (logo + tekst) | - |
| **Ryzyko** | 🔴 Wysokie (podwójne koszty) | - |

**Wniosek:** ❌ **NIE MA SENSU** - podwójne koszty + wymagana licencja TMDB

**Uwaga:** RapidAPI zostało usunięte z projektu. Dla portfolio/demo używamy lokalnych API keys.

### Scenariusz 4: Serwis na platformie API używający TVmaze (historyczne)

| Aspekt | Status | Koszt |
|--------|--------|-------|
| **Legalność** | ✅ Dozwolone (CC BY-SA) | Darmowe |
| **Platforma API** | ⚠️ Dodatkowe koszty platformy | + koszty platformy |
| **Atrybucja** | ✅ Wymagana (link do TVmaze) | - |
| **Ryzyko** | 🟡 Średnie (niepotrzebne koszty platformy) | - |

**Wniosek:** ⚠️ **MA SENSU** tylko jeśli platforma API daje wartość (cache, monitoring, itp.)

**Uwaga:** RapidAPI zostało usunięte z projektu. Dla portfolio/demo używamy lokalnych API keys.

---

## 📊 Rekomendacje

### ✅ Rekomendowane podejście dla serwisu komercyjnego

#### Opcja 1: TVmaze (zalecane dla startu)

**Zalety:**
- ✅ Legalne komercyjne wykorzystanie (CC BY-SA)
- ✅ Darmowe
- ✅ Proste wymagania atrybucji
- ✅ Dobra dokumentacja API

**Wady:**
- ⚠️ Rate limit: 20 req/10s (może wymagać cache'owania)
- ⚠️ ShareAlike (tylko jeśli redystrybuujesz dane)
- ⚠️ Mniejsza baza danych niż TMDB (głównie seriale TV)

**Kiedy używać:**
- Startupy z ograniczonym budżetem
- Aplikacje skupione na serialach TV
- Projekty gdzie ShareAlike nie jest problemem

#### Opcja 2: TMDB z licencją komercyjną

**Zalety:**
- ✅ Największa baza danych filmów/seriali
- ✅ Wysoka jakość danych
- ✅ Brak ograniczeń ShareAlike
- ✅ Profesjonalne wsparcie

**Wady:**
- ❌ Wymaga licencji komercyjnej (~$149-3500/miesiąc)
- ❌ Proces negocjacji (czasochłonne)
- ❌ Wymagana atrybucja (logo + tekst)

**Kiedy używać:**
- Projekty z budżetem na licencję
- Aplikacje wymagające największej bazy danych
- Projekty gdzie ShareAlike jest problemem

#### Opcja 3: Hybrydowe podejście

**Strategia:**
- TVmaze dla danych podstawowych (darmowe)
- TMDB dla uzupełnień (z licencją komercyjną)
- Własne dane AI-generated (MovieMind API)

**Zalety:**
- ✅ Optymalizacja kosztów
- ✅ Najlepsze z obu światów
- ✅ Redundancja danych

**Wady:**
- ⚠️ Większa złożoność techniczna
- ⚠️ Wymaga zarządzania dwoma źródłami

### ❌ NIE rekomendowane podejścia

1. **TMDB bez licencji komercyjnej w projekcie z reklamami:**
   - Naruszenie warunków użytkowania
   - Ryzyko prawne
   - Możliwość zablokowania API

2. **Platforma API jako obejście licencji TMDB:**
   - Nie zwalnia z obowiązku licencji
   - Dodatkowe koszty
   - Podwójne ryzyko prawne
   - **Uwaga:** RapidAPI zostało usunięte z projektu

3. **Ignorowanie atrybucji:**
   - Naruszenie licencji (zarówno TMDB jak i TVmaze)
   - Ryzyko prawne
   - Utrata dostępu do API

---

## 🎯 Konkretne rekomendacje dla MovieMind API

### Obecna sytuacja projektu

Zgodnie z analizą projektu MovieMind API:
- Generuje **własne opisy AI** (nie kopiuje z TMDB/TVmaze)
- Używa danych z TMDB/TVmaze prawdopodobnie do metadanych (tytuły, daty, obsada)
- **Portfolio/Demo:** Subskrypcje zarządzane lokalnie przez API keys
- **Produkcja:** Możliwa integracja z billing providerami (Stripe, PayPal)

### Rekomendowane działania

#### 1. Dla niekomercyjnego MVP/Prototypu

✅ **Użyj TVmaze:**
- Darmowe i legalne
- Wystarczające dla prototypu
- Proste wymagania atrybucji

#### 2. Dla komercyjnego produktu z reklamami

**Opcja A (budżet ograniczony):**
- ✅ TVmaze jako główne źródło metadanych
- ✅ Własne opisy AI (MovieMind)
- ✅ Atrybucja TVmaze w UI
- ⚠️ Cache'owanie agresywne (rate limits)

**Opcja B (budżet dostępny):**
- ✅ TMDB z licencją komercyjną (~$149-3500/miesiąc)
- ✅ Własne opisy AI (MovieMind)
- ✅ Atrybucja TMDB w UI
- ✅ Większa baza danych

#### 3. Dla monetyzacji przez RapidAPI

**Strategia:**
- ✅ Użyj TVmaze (darmowe) jako źródło metadanych
- ✅ Własne opisy AI jako wartość dodana
- ✅ RapidAPI jako platforma dystrybucji
- ✅ Atrybucja TVmaze w dokumentacji API

**Dlaczego to działa:**
- TVmaze pozwala na komercyjne wykorzystanie
- Własne opisy AI to unikalna wartość
- Platformy API (np. RapidAPI, Stripe) to tylko platformy (nie zmieniają licencji danych źródłowych)
- **Uwaga:** RapidAPI zostało usunięte z projektu

---

## 📝 Checklist zgodności

### Dla TVmaze (CC BY-SA)

- [ ] Link do TVmaze w aplikacji/stronie
- [ ] Atrybucja widoczna dla użytkowników
- [ ] Jeśli redystrybuujesz dane: użyj licencji CC BY-SA
- [ ] Respektuj rate limits (20 req/10s)
- [ ] Ustaw User-Agent w zapytaniach HTTP

### Dla TMDB (komercyjnie)

- [ ] Pisemna umowa z TMDB (sales@themoviedb.org)
- [ ] Logo TMDB w aplikacji (mniej prominentne)
- [ ] Tekst atrybucji: "This [app] uses TMDB and the TMDB APIs but is not endorsed, certified, or otherwise approved by TMDB."
- [ ] Link do https://www.themoviedb.org
- [ ] Cache nie dłużej niż 6 miesięcy
- [ ] Regularne płatności za licencję

### Dla TMDB (niekomercyjnie)

- [ ] Brak monetyzacji (reklamy, subskrypcje, płatności)
- [ ] Logo TMDB w aplikacji
- [ ] Tekst atrybucji
- [ ] Link do https://www.themoviedb.org
- [ ] Cache nie dłużej niż 6 miesięcy

---

## 🔗 Źródła i linki

### Oficjalne dokumenty

- [TMDB API Terms of Use](https://www.themoviedb.org/api-terms-of-use)
- [TMDB Developer FAQ](https://developer.themoviedb.org/docs/faq)
- [TVmaze API Documentation](https://www.tvmaze.com/api)
- [Creative Commons CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/)

### Kontakty

- **TMDB Sales:** sales@themoviedb.org
- **TVmaze Enterprise:** contact via [tvmaze.com/api](https://www.tvmaze.com/api#enterprise-api)

### Raporty użytkowników

- [Reddit: TMDB Commercial License Discussion](https://www.reddit.com/r/iOSProgramming/comments/1jpke10/)

---

## ⚖️ Disclaimer prawny

**UWAGA:** Ta analiza ma charakter informacyjny i nie stanowi porady prawnej. Przed podjęciem decyzji o komercyjnym wykorzystaniu API:

1. ✅ Skonsultuj się z prawnikiem specjalizującym się w prawie własności intelektualnej
2. ✅ Przeczytaj aktualne warunki użytkowania obu serwisów
3. ✅ Skontaktuj się bezpośrednio z TMDB/TVmaze w sprawie licencji
4. ✅ Upewnij się, że rozumiesz wszystkie wymagania licencyjne

**Data analizy:** 2026-01-21  
**Ostatnia aktualizacja warunków:** Sprawdź oficjalne strony przed użyciem

---

## 📌 Podsumowanie końcowe

### ✅ Tak, możesz stworzyć serwis komercyjny z monetyzacją

**Najlepsze opcje:**

1. **TVmaze + własne opisy AI:**
   - ✅ Legalne i darmowe
   - ✅ Idealne dla startu
   - ⚠️ Ograniczenia rate limits

2. **TMDB z licencją komercyjną:**
   - ✅ Największa baza danych
   - ⚠️ Wymaga budżetu (~$149-3500/miesiąc)
   - ✅ Profesjonalne wsparcie

### ❌ Nie, nie możesz używać TMDB komercyjnie bez licencji

- Nawet aplikacje z reklamami wymagają licencji
- Platformy API (np. RapidAPI) nie zwalniają z obowiązku licencji
- **Uwaga:** RapidAPI zostało usunięte z projektu
- Naruszenie = ryzyko prawne + blokada API

### 💡 Rekomendacja dla MovieMind API

**Dla MVP/Prototypu:**
- Użyj TVmaze (darmowe, legalne)

**Dla produktu komercyjnego:**
- Rozważ hybrydowe podejście: TVmaze + własne opisy AI
- Jeśli budżet pozwala: TMDB z licencją komercyjną

**Dla portfolio/demo:**
- TVmaze jako źródło metadanych
- Własne opisy AI jako unikalna wartość
- Lokalne API keys do zarządzania subskrypcjami
- **Uwaga:** RapidAPI zostało usunięte z projektu

---

*Dokument utworzony: 2026-01-21*  
*Autor: Analiza na podstawie oficjalnych dokumentów i raportów użytkowników*
