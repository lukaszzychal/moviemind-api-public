# 🔄 Refresh vs Generate - Różnice

## POST /api/v1/generate

**Cel:** Generuje NOWY opis/bio używając AI

**Co robi:**
1. Tworzy nowy job w kolejce (`RealGenerateMovieJob` / `RealGeneratePersonJob`)
2. Job wywołuje AI API (OpenAI) do wygenerowania opisu
3. Tworzy/aktualizuje encję (Movie/Person) w bazie
4. Tworzy nowy opis/bio w bazie
5. Zwraca `job_id` - klient musi sprawdzić status joba

**Kiedy używać:**
- Chcesz wygenerować NOWY opis dla filmu/osoby
- Chcesz wygenerować opis w innym języku (`locale`)
- Chcesz wygenerować opis w innym stylu (`context_tag`: modern, critical, humorous)
- Film/osoba już istnieje, ale chcesz nowy opis

**Przykład:**
```bash
POST /api/v1/generate
{
  "entity_type": "MOVIE",
  "slug": "the-matrix",
  "locale": "pl-PL",
  "context_tag": "modern"
}
```

**Odpowiedź:**
```json
{
  "job_id": "abc-123",
  "status": "PENDING"
}
```

---

## POST /api/v1/movies/{slug}/refresh
## POST /api/v1/people/{slug}/refresh

**Cel:** Odświeża dane TMDb (tylko snapshot, NIE generuje nowego opisu)

**Co robi:**
1. Sprawdza czy film/osoba istnieje w bazie
2. Sprawdza czy istnieje snapshot TMDb
3. Pobiera najnowsze dane z TMDb API
4. Aktualizuje snapshot w bazie (`tmdb_snapshots.raw_data`)
5. Aktualizuje `fetched_at` timestamp
6. Czyści cache
7. **NIE generuje nowego opisu AI**

**Kiedy używać:**
- Chcesz zaktualizować dane TMDb (np. nowe informacje o filmie)
- Chcesz zsynchronizować dane z TMDb
- Film/osoba już istnieje i ma snapshot

**Przykład:**
```bash
POST /api/v1/movies/the-matrix/refresh
```

**Odpowiedź:**
```json
{
  "message": "Movie data refreshed from TMDb",
  "slug": "the-matrix",
  "movie_id": 123,
  "tmdb_id": 603,
  "refreshed_at": "2025-12-17T03:00:00Z"
}
```

---

## 📊 Porównanie

| Aspekt | Generate | Refresh |
|--------|----------|---------|
| **Tworzy nowy opis AI** | ✅ Tak | ❌ Nie |
| **Aktualizuje dane TMDb** | ❌ Nie (tylko przy pierwszym tworzeniu) | ✅ Tak |
| **Tworzy job w kolejce** | ✅ Tak | ❌ Nie |
| **Wymaga sprawdzenia statusu** | ✅ Tak (job_id) | ❌ Nie (synchronous) |
| **Może zmienić locale/context** | ✅ Tak | ❌ Nie |
| **Aktualizuje snapshot** | ✅ Tak (przy pierwszym tworzeniu) | ✅ Tak |
| **Czyści cache** | ✅ Tak | ✅ Tak |

---

## 💡 Kiedy używać którego?

### Użyj `generate` gdy:
- Chcesz wygenerować NOWY opis AI
- Chcesz opis w innym języku
- Chcesz opis w innym stylu
- Film/osoba nie istnieje jeszcze w bazie

### Użyj `refresh` gdy:
- Chcesz zaktualizować dane TMDb (np. nowe informacje)
- Chcesz zsynchronizować dane z TMDb
- Film/osoba już istnieje i ma snapshot
- **NIE chcesz generować nowego opisu AI**

---

## 🔄 Workflow Przykład

1. **Pierwsze utworzenie:**
   ```
   GET /api/v1/movies/the-matrix
   → 202 Accepted (job queued)
   → Job tworzy Movie + MovieDescription + Snapshot
   ```

2. **Odświeżenie danych TMDb:**
   ```
   POST /api/v1/movies/the-matrix/refresh
   → 200 OK (snapshot updated)
   → Tylko snapshot zaktualizowany, opis AI bez zmian
   ```

3. **Generowanie nowego opisu:**
   ```
   POST /api/v1/generate
   {
     "entity_type": "MOVIE",
     "slug": "the-matrix",
     "locale": "pl-PL"
   }
   → 202 Accepted (job queued)
   → Job tworzy NOWY MovieDescription w języku polskim
   ```

