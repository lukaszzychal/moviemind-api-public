# Postman API Testing Guide

## 📚 Spis treści

1. [Czym jest Postman i Newman?](#czym-jest-postman-i-newman)
2. [Jak uruchomić testy w Postman GUI](#jak-uruchomić-testy-w-postman-gui)
3. [Jak uruchomić testy przez Newman (CLI)](#jak-uruchomić-testy-przez-newman-cli)
4. [Czy można użyć Insomnia?](#czy-można-użyć-insomnia)
5. [Jak działają testy w Postman](#jak-działają-testy-w-postman)
6. [Jak pisać testy](#jak-pisać-testy)
7. [Struktura pliku kolekcji](#struktura-pliku-kolekcji)
8. [Przykłady z MovieMind API](#przykłady-z-moviemind-api)

---

## Czym jest Postman i Newman?

### Postman

**Postman** to narzędzie do testowania API z graficznym interfejsem użytkownika (GUI). Pozwala na:
- Tworzenie i wysyłanie żądań HTTP
- Organizowanie żądań w kolekcje
- Pisanie testów w JavaScript
- Automatyzację testów
- Zarządzanie zmiennymi środowiskowymi
- Dokumentację API

### Newman

**Newman** to CLI (Command Line Interface) dla Postman. Pozwala na:
- Uruchamianie kolekcji Postman z linii poleceń
- Integrację z CI/CD (np. GitHub Actions)
- Automatyzację testów bez GUI
- Generowanie raportów (JUnit XML, HTML, JSON)

**Różnica:**
- **Postman** = GUI do tworzenia i testowania API
- **Newman** = CLI do uruchamiania testów z linii poleceń / CI

---

## Jak uruchomić testy w Postman GUI

### 1. Instalacja Postman

1. Pobierz Postman: https://www.postman.com/downloads/
2. Zainstaluj aplikację
3. Utwórz konto (opcjonalne, ale zalecane)

### 2. Import kolekcji

1. Otwórz Postman
2. Kliknij **Import** (lewy górny róg)
3. Wybierz plik `docs/postman/moviemind-api.postman_collection.json`
4. Kliknij **Import**

### 3. Import środowiska

1. Kliknij **Import** ponownie
2. Wybierz plik `docs/postman/environments/local.postman_environment.json`
3. Kliknij **Import**

### 4. Konfiguracja środowiska

1. Kliknij ikonę **oko** (Environment quick look) w prawym górnym rogu
2. Wybierz środowisko **"MovieMind - Local"**
3. Sprawdź/zmień wartości:
   - `baseUrl`: `http://localhost:8000`
   - `adminApiKey`: (opcjonalne, jeśli potrzebne)

### 5. Uruchomienie pojedynczego testu

1. Wybierz żądanie z kolekcji (np. "Movies / Get movie by slug")
2. Kliknij **Send**
3. Sprawdź zakładkę **Test Results** poniżej odpowiedzi
4. Zobaczysz wyniki testów (✅ pass / ❌ fail)

### 6. Uruchomienie całej kolekcji

1. Kliknij prawym przyciskiem na kolekcję **"MovieMind API"**
2. Wybierz **Run collection**
3. W oknie **Collection Runner**:
   - Wybierz środowisko (jeśli potrzebne)
   - Kliknij **Run MovieMind API**
4. Zobaczysz wyniki wszystkich testów

### 7. Uruchomienie z automatycznym raportem

1. W **Collection Runner** kliknij **Run**
2. Po zakończeniu zobaczysz:
   - Lista wszystkich żądań
   - Status każdego testu
   - Statystyki (pass/fail)
   - Czas wykonania

---

## Jak uruchomić testy przez Newman (CLI)

### 1. Instalacja Newman

```bash
# Globalna instalacja
npm install -g newman

# Lub lokalna (w projekcie)
npm install --save-dev newman newman-reporter-junit
```

### 2. Podstawowe uruchomienie

```bash
# Uruchomienie kolekcji
newman run docs/postman/moviemind-api.postman_collection.json

# Z środowiskiem
newman run docs/postman/moviemind-api.postman_collection.json \
  -e docs/postman/environments/local.postman_environment.json

# Z nadpisaniem zmiennych środowiskowych
newman run docs/postman/moviemind-api.postman_collection.json \
  -e docs/postman/environments/local.postman_environment.json \
  --env-var "baseUrl=http://localhost:8000"
```

### 3. Generowanie raportów

```bash
# Raport JUnit XML (dla CI)
newman run docs/postman/moviemind-api.postman_collection.json \
  -e docs/postman/environments/local.postman_environment.json \
  --reporters cli,junit \
  --reporter-junit-export newman-results.xml

# Raport HTML
newman run docs/postman/moviemind-api.postman_collection.json \
  -e docs/postman/environments/local.postman_environment.json \
  --reporters cli,html \
  --reporter-html-export newman-results.html
```

### 4. Przykład z projektu (CI)

W `.github/workflows/ci.yml`:

```yaml
- name: Run Newman tests
  run: |
    npx --yes newman@latest run docs/postman/moviemind-api.postman_collection.json \
      -e docs/postman/environments/local.postman_environment.json \
      --env-var "baseUrl=http://localhost:8000" \
      --reporters cli,junit \
      --reporter-junit-export newman-results.xml
```

---

## Czy można użyć Insomnia?

**Krótka odpowiedź: NIE** - Insomnia nie obsługuje kolekcji Postman bezpośrednio.

### Alternatywy:

1. **Postman** (zalecane) - pełna obsługa testów
2. **Newman CLI** - uruchamianie z linii poleceń
3. **Bruno** - alternatywa open-source z podobną funkcjonalnością
4. **REST Client (VS Code)** - rozszerzenie do VS Code

### Konwersja Insomnia → Postman:

Możesz eksportować z Insomnia do OpenAPI/Swagger, a następnie zaimportować do Postman, ale **testy JavaScript nie będą działać**.

---

## Jak działają testy w Postman

### 1. Struktura żądania

Każde żądanie w Postman składa się z:
- **Request** - URL, metoda HTTP, headers, body
- **Pre-request Script** - kod wykonywany PRZED żądaniem
- **Tests** - kod wykonywany PO otrzymaniu odpowiedzi

### 2. Przepływ wykonania

```
1. Pre-request Script (opcjonalny)
   ↓
2. Wysłanie żądania HTTP
   ↓
3. Otrzymanie odpowiedzi
   ↓
4. Tests (weryfikacja odpowiedzi)
   ↓
5. Zapisywanie zmiennych (opcjonalne)
```

### 3. Zmienne w Postman

**Typy zmiennych:**
- **Collection Variables** - dostępne w całej kolekcji
- **Environment Variables** - zależne od środowiska
- **Global Variables** - dostępne wszędzie
- **Local Variables** - tylko w bieżącym żądaniu

**Priorytet:** Local > Environment > Collection > Global

---

## Jak pisać testy

### Podstawowa składnia

```javascript
// Test pojedynczy
pm.test("Nazwa testu", function () {
  pm.expect(wartość).to.eql(oczekiwana_wartość);
});

// Test statusu
pm.test("Status code is 200", function () {
  pm.response.to.have.status(200);
});

// Test odpowiedzi JSON
pm.test("Response contains data", function () {
  const json = pm.response.json();
  pm.expect(json).to.have.property('data');
});
```

### Dostępne obiekty

#### `pm.response`
```javascript
pm.response.code          // Status code (200, 404, etc.)
pm.response.status()      // Status text ("OK", "Not Found")
pm.response.headers       // Headers odpowiedzi
pm.response.json()        // Parsed JSON body
pm.response.text()        // Raw text body
pm.response.responseTime  // Czas odpowiedzi w ms
```

#### `pm.request`
```javascript
pm.request.url            // URL żądania
pm.request.method         // Metoda HTTP (GET, POST, etc.)
pm.request.headers        // Headery żądania
pm.request.body           // Body żądania
```

#### `pm.collectionVariables`
```javascript
// Pobranie zmiennej
const value = pm.collectionVariables.get('movieSlug');

// Ustawienie zmiennej
pm.collectionVariables.set('movieSlug', 'the-matrix-1999');
```

#### `pm.environment`
```javascript
// Pobranie zmiennej środowiskowej
const baseUrl = pm.environment.get('baseUrl');

// Ustawienie zmiennej środowiskowej
pm.environment.set('baseUrl', 'http://localhost:8000');
```

### Asercje (Chai.js)

Postman używa biblioteki **Chai.js** do asercji:

```javascript
// Równość
pm.expect(value).to.eql(expected);
pm.expect(value).to.equal(expected);

// Właściwości obiektu
pm.expect(obj).to.have.property('key');
pm.expect(obj).to.have.property('key', 'value');

// Typy
pm.expect(arr).to.be.an('array');
pm.expect(str).to.be.a('string');

// Zawartość
pm.expect(arr).to.include(item);
pm.expect(str).to.include('substring');

// Status code
pm.response.to.have.status(200);
pm.response.to.have.status([200, 201, 202]);
```

### Przykłady testów

#### 1. Podstawowy test statusu
```javascript
pm.test("Status code is 200", function () {
  pm.response.to.have.status(200);
});
```

#### 2. Test struktury odpowiedzi
```javascript
pm.test("Response contains data array", function () {
  const json = pm.response.json();
  pm.expect(json).to.have.property('data').that.is.an('array');
});
```

#### 3. Test z warunkiem
```javascript
pm.test("Response contains id when status is 200", function () {
  if (pm.response.code === 200) {
    const json = pm.response.json();
    pm.expect(json).to.have.property('id');
  }
});
```

#### 4. Test z użyciem zmiennych
```javascript
pm.test("Response slug matches expected", function () {
  const json = pm.response.json();
  const expectedSlug = pm.collectionVariables.get('movieSlug');
  pm.expect(json.slug).to.eql(expectedSlug);
});
```

#### 5. Zapisywanie wartości do zmiennych
```javascript
const json = pm.response.json();
if (json.id) {
  pm.collectionVariables.set('movieId', json.id);
}
if (json.default_description && json.default_description.id) {
  pm.collectionVariables.set('movieDefaultDescriptionId', json.default_description.id);
}
```

---

## Struktura pliku kolekcji

Plik kolekcji Postman to plik JSON z następującą strukturą:

```json
{
  "info": {
    "name": "MovieMind API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
    "version": "1.2.0"
  },
  "item": [
    {
      "name": "Movies",
      "item": [
        {
          "name": "Get movie by slug",
          "request": {
            "method": "GET",
            "url": {
              "raw": "{{baseUrl}}/api/v1/movies/{{movieSlug}}",
              "host": ["{{baseUrl}}"],
              "path": ["api", "v1", "movies", "{{movieSlug}}"]
            },
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              }
            ]
          },
          "event": [
            {
              "listen": "test",
              "script": {
                "type": "text/javascript",
                "exec": [
                  "pm.test(\"Status code is 200\", function () {",
                  "  pm.response.to.have.status(200);",
                  "});"
                ]
              }
            }
          ]
        }
      ]
    }
  ],
  "variable": [
    {
      "key": "movieSlug",
      "value": "the-matrix-1999",
      "type": "string"
    }
  ]
}
```

### Główne sekcje:

1. **`info`** - metadane kolekcji
2. **`item`** - lista żądań/folderów
3. **`variable`** - zmienne kolekcji
4. **`event`** - pre-request scripts i tests

### Struktura żądania:

```json
{
  "name": "Nazwa żądania",
  "request": {
    "method": "GET|POST|PUT|DELETE|PATCH",
    "url": { ... },
    "header": [ ... ],
    "body": { ... }
  },
  "event": [
    {
      "listen": "prerequest",  // Kod PRZED żądaniem
      "script": { ... }
    },
    {
      "listen": "test",         // Kod PO odpowiedzi
      "script": { ... }
    }
  ]
}
```

---

## Przykłady z MovieMind API

### Przykład 1: Test statusu i struktury

```javascript
// Z: docs/postman/moviemind-api.postman_collection.json
pm.test("Status code is 200", function () {
  pm.response.to.have.status(200);
});

const json = pm.response.json();
pm.test("Response contains movie id and slug", function () {
  pm.expect(json).to.have.property('id');
  pm.expect(json).to.have.property('slug');
});
```

### Przykład 2: Test z warunkiem i zmiennymi

```javascript
// Z: "Get movie by slug (selected description)"
pm.test("Status code is 200", function () {
  pm.response.to.have.status(200);
});

const json = pm.response.json();
const descriptionIdVar = pm.collectionVariables.get('movieDescriptionId') 
  || pm.collectionVariables.get('movieDefaultDescriptionId');
const urlParams = pm.request.url.query.get('description_id');

if (descriptionIdVar && urlParams) {
  const descriptionId = Number(descriptionIdVar);
  pm.test("Selected description returned", function () {
    pm.expect(json).to.have.property('selected_description');
  });
  if (json.selected_description) {
    pm.test("Selected description id matches", function () {
      pm.expect(json.selected_description.id).to.eql(descriptionId);
    });
  }
}
```

### Przykład 3: Test z zapisywaniem zmiennych

```javascript
// Z: "List movies"
const json = pm.response.json();
if (Array.isArray(json.data) && json.data.length > 0) {
  const first = json.data[0];
  if (first.slug) {
    pm.collectionVariables.set('movieSlug', first.slug);
  }
  if (first.id) {
    pm.collectionVariables.set('movieId', first.id);
  }
  if (first.default_description && first.default_description.id) {
    pm.collectionVariables.set('movieDefaultDescriptionId', first.default_description.id);
  }
}
```

### Przykład 4: Test z obsługą wielu statusów

```javascript
// Z: "Get person by slug"
pm.test("Status code is 200 or 202", function () {
  pm.expect([200, 202]).to.include(pm.response.code);
});

const json = pm.response.json();
if (pm.response.code === 200) {
  pm.test("Response contains person id and slug", function () {
    pm.expect(json).to.have.property('id');
    pm.expect(json).to.have.property('slug');
  });
  pm.test("Response exposes bios_count", function () {
    pm.expect(json).to.have.property('bios_count');
  });
}
```

---

## Najlepsze praktyki

### 1. Organizacja testów
- Grupuj testy logicznie (status, struktura, wartości)
- Używaj opisowych nazw testów
- Testuj jeden aspekt na test

### 2. Zmienne
- Używaj zmiennych zamiast hardkodowanych wartości
- Zapisz wartości z odpowiedzi do użycia w kolejnych żądaniach
- Używaj odpowiedniego typu zmiennej (collection vs environment)

### 3. Obsługa błędów
- Testuj zarówno sukces (200) jak i błędy (404, 500)
- Sprawdzaj warunki przed testowaniem właściwości
- Używaj `if` do warunkowych testów

### 4. Czytelność
- Formatuj kod JavaScript czytelnie
- Dodawaj komentarze dla złożonych testów
- Używaj opisowych nazw zmiennych

### 5. CI/CD
- Używaj Newman do automatycznych testów
- Generuj raporty JUnit XML dla CI
- Ustaw odpowiednie timeouty dla żądań

---

## Przydatne linki

- [Postman Documentation](https://learning.postman.com/docs/)
- [Newman Documentation](https://github.com/postmanlabs/newman)
- [Postman Scripting](https://learning.postman.com/docs/writing-scripts/script-references/test-examples/)
- [Chai.js Assertions](https://www.chaijs.com/api/bdd/)

---

## Podsumowanie

1. **Postman** = GUI do tworzenia i testowania API
2. **Newman** = CLI do uruchamiania testów (CI/CD)
3. **Testy** = JavaScript wykonywany po otrzymaniu odpowiedzi
4. **Zmienne** = sposób na przekazywanie danych między żądaniami
5. **Asercje** = Chai.js do weryfikacji odpowiedzi

**Nie można użyć Insomnia** - tylko Postman/Newman obsługują testy JavaScript.

