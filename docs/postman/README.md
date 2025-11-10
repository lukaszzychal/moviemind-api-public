# Postman – MovieMind API

*English version: [README.en.md](./README.en.md)*

## 📦 Zawartość
- `moviemind-api.postman_collection.json` – główna kolekcja zapytań
- `environments/local.postman_environment.json` – szablon środowiska lokalnego
- `environments/staging.postman_environment.json` – szablon środowiska staging

## 🚀 Import w Postmanie
1. Otwórz Postmana i wybierz **Import** → **File**.
2. Wskaż `docs/postman/moviemind-api.postman_collection.json`.
3. W zakładce **Environments** zaimportuj wybrany szablon (`local` lub `staging`).
4. Skopiuj plik środowiska i uzupełnij go prywatnie (np. `local.postman_environment.private.json` z prawdziwym kluczem API). Nie commituj prywatnych plików.
5. Aktywuj środowisko, uruchom kolekcję i sprawdź, czy zmienna `baseUrl` wskazuje poprawną instancję API.

## ✅ Testy i zmienne
- Każde żądanie ma wbudowane testy walidujące kod HTTP i podstawową strukturę JSON.
- Kluczowe wartości (np. `movieSlug`, `jobId`, `movieDescriptionId`, `personBioId`) są automatycznie zapisywane jako zmienne kolekcji.
- `movieDescriptionId` / `personBioId` przechowują identyfikatory bazowych opisów/bio i pozwalają odpytać wariant poprzez `description_id` / `bio_id`.
- Aby zresetować stan, wyczyść zmienne kolekcji w panelu Postmana (**Collections → Variables**).

## 🎯 Warianty opisów i bio
- Nowe requesty **Get movie by slug (selected description)** oraz **Get person by slug (selected bio)** demonstrują parametry `description_id` / `bio_id`.
- Najpierw uruchom jedno z żądań generujących (`Generate movie/person (existing slug -> 202)`), aby zapisać baseline ID do zmiennych kolekcji.
- Następnie wywołaj wariant `GET` z parametrem – odpowiedź zawiera pole `selected_description` lub `selected_bio` z danymi wskazanego wariantu.

## 🧪 Uruchamianie Newmanem
```bash
newman run docs/postman/moviemind-api.postman_collection.json \
  -e docs/postman/environments/local.postman_environment.json \
  --reporters cli
```
W przypadku stagingu podmień ścieżkę do pliku environmentu lub użyj własnego pliku ze zmiennymi sekretów.

## 🔐 Wrażliwe dane
- Szablony środowisk zawierają wyłącznie placeholdery (`{{ADMIN_API_KEY}}`).
- Prawdziwe klucze trzymaj w prywatnych plikach ignorowanych przez Git.
- Nie commituj plików `.postman_environment.json` zawierających sekrety.
