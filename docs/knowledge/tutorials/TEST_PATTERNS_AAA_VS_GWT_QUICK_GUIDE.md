# AAA vs GWT - Szybki przewodnik różnic

> **Krótkie wyjaśnienie różnic między wzorcami AAA i GWT**

---

## 🤔 Czy to jest to samo?

**Krótka odpowiedź:** Nie, ale są **bardzo podobne**.

Oba wzorce dzielą test na **3 fazy**, ale różnią się:
- **Terminologią** (językiem)
- **Pochodzeniem** (kto je stworzył i po co)
- **Filozofią** (na czym się skupiają)

---

## 📊 Porównanie - Side by Side

### Koncepcyjnie - IDENTYCZNE ✅

Oba wzorce mają **dokładnie tę samą strukturę**:

```
1. PRZYGOTOWANIE (ustawienie stanu początkowego)
2. WYKONANIE (uruchomienie akcji)
3. WERYFIKACJA (sprawdzenie wyniku)
```

### Różnice - JĘZYK I FILOZOFIA ❌

| Aspekt | AAA (Arrange-Act-Assert) | GWT (Given-When-Then) |
|--------|-------------------------|----------------------|
| **Nazwa fazy 1** | **Arrange** (Przygotuj) | **Given** (Zakładając, że...) |
| **Nazwa fazy 2** | **Act** (Wykonaj) | **When** (Kiedy...) |
| **Nazwa fazy 3** | **Assert** (Sprawdź) | **Then** (Wtedy...) |
| **Język** | Techniczny (dla programistów) | Naturalny (dla wszystkich) |
| **Pochodzenie** | Tradycyjne testy jednostkowe | BDD (Behavior-Driven Development) |
| **Fokus** | Struktura kodu | Zachowanie systemu |
| **Komentarze** | Opcjonalne | Zalecane (czytelność) |

---

## 💻 Praktyczne przykłady

### Ten sam test w obu wzorcach:

#### Wersja AAA (Arrange-Act-Assert)

```php
public function test_movie_generation_is_queued(): void
{
    // ARRANGE: Przygotuj stan początkowy
    Feature::activate('ai_description_generation');
    $this->assertDatabaseMissing('movies', ['slug' => 'the-matrix-1999']);
    
    // ACT: Wykonaj akcję
    $response = $this->getJson('/api/v1/movies/the-matrix-1999');
    
    // ASSERT: Sprawdź wynik
    Queue::assertPushed(GenerateMovieJob::class);
    $response->assertStatus(202);
}
```

#### Wersja GWT (Given-When-Then)

```php
public function test_movie_generation_is_queued(): void
{
    // GIVEN: Film nie istnieje w bazie danych
    Feature::activate('ai_description_generation');
    $this->assertDatabaseMissing('movies', ['slug' => 'the-matrix-1999']);
    
    // WHEN: Wysyłane jest żądanie o film
    $response = $this->getJson('/api/v1/movies/the-matrix-1999');
    
    // THEN: Job generowania powinien być dodany do kolejki
    Queue::assertPushed(GenerateMovieJob::class);
    
    // THEN: Odpowiedź powinna wskazywać status 202
    $response->assertStatus(202);
}
```

### 🔍 Co się zmieniło?

**Kod jest IDENTYCZNY** - zmieniły się tylko **komentarze** i **nazewnictwo**!

---

## 🎯 Kluczowe różnice

### 1. **Terminologia**

**AAA:**
- Arrange = "Przygotuj/ustaw"
- Act = "Wykonaj/aktywuj"
- Assert = "Sprawdź/asercja"

**GWT:**
- Given = "Zakładając, że..." / "Mając..."
- When = "Kiedy..." / "Gdy..."
- Then = "Wtedy..." / "Powinno..."

**GWT używa języka naturalnego** - można to przeczytać jak historię:
> "**GIVEN** film nie istnieje, **WHEN** żądamy filmu, **THEN** job powinien być zakolejkowany"

### 2. **Filozofia**

**AAA:**
- Skupia się na **strukturze kodu**
- Myśli w kategoriach: "co robię" (prepare → execute → verify)
- Podejście techniczne

**GWT:**
- Skupia się na **zachowaniu systemu**
- Myśli w kategoriach: "co powinno się stać" (scenario → action → outcome)
- Podejście biznesowe/zachowawcze

### 3. **Czytelność**

**AAA:**
- ✅ Dla programistów - naturalne
- ❌ Dla biznesu/stakeholderów - techniczne terminy

**GWT:**
- ✅ Dla wszystkich - język naturalny
- ✅ Dla biznesu - czytelne jak specyfikacja
- ✅ Dla QA - łatwe do zrozumienia

### 4. **Kiedy używać**

**AAA - lepsze dla:**
- Testów jednostkowych (simple, isolated)
- Szybkich, prostych testów
- Gdy kod jest sam w sobie czytelny

**GWT - lepsze dla:**
- Testów funkcjonalnych (complex scenarios)
- Gdy testy mają być czytelne dla non-technical osób
- Gdy chcemy dokumentować zachowanie systemu
- W środowisku BDD (Behavior-Driven Development)

---

## 🤝 Podsumowanie

### Czy to jest to samo?

**Strukturalnie:** ✅ **TAK** - oba dzielą test na 3 fazy  
**Praktycznie:** ⚠️ **PRAWIE** - kod jest identyczny  
**Filozoficznie:** ❌ **NIE** - różna terminologia i fokus

### Analogia:

To jak powiedzieć:
- **AAA:** "Przygotuj → Wykonaj → Sprawdź" (techniczne)
- **GWT:** "Zakładając → Kiedy → Wtedy" (naturalne)

**To ta sama czynność**, ale opisana **innym językiem** z **innym nastawieniem**.

---

## 📚 Dalsze informacje

Szczegółowy tutorial: [`TEST_PATTERNS_AAA_GWT_TUTORIAL.md`](./TEST_PATTERNS_AAA_GWT_TUTORIAL.md)

