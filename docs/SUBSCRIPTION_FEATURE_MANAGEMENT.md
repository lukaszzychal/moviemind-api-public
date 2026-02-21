# Zarządzanie Funkcjami Subskrypcji

Utworzono endpointy oraz testy pozwalające na zarządzanie funkcjami planów subskrypcyjnych (Subscription Plans), co umożliwia przypisywanie uprawnień takich jak `ai_generate` do konkretnych planów.

## Zmiany w kodzie

### 1. `SubscriptionPlanController`
Nowy kontroler w `api/app/Http/Controllers/Admin/SubscriptionPlanController.php` obsługuje:
- `GET /api/v1/admin/subscription-plans` - lista planów
- `GET /api/v1/admin/subscription-plans/{id}` - szczegóły planu
- `POST /api/v1/admin/subscription-plans/{id}/features` - dodawanie funkcji
- `DELETE /api/v1/admin/subscription-plans/{id}/features/{feature}` - usuwanie funkcji

### 2. `api/routes/api.php`
Dodano routing dla powyższych endpointów w grupie admina (`v1/admin/subscription-plans`).

### 3. Testy Automatyczne (`SubscriptionPlanTest.php`)
Utworzono testy funkcjonalne w `api/tests/Feature/Admin/SubscriptionPlanTest.php` obejmujące:
- Listing planów
- Pobieranie pojedynczego planu
- Dodawanie funkcji (z weryfikacją duplikatów)
- Usuwanie funkcji
- Weryfikację uprawnień (z użyciem tokena admina)

Poprawiono również migrację `2026_01_27_174000_add_id_to_features_table.php`, aby działała poprawnie z bazą SQLite używaną w testach.

### 4. Skrypt Instalasji (`setup-local-testing.sh`)
Zaktualizowano skrypt `scripts/setup-local-testing.sh`:
- Automatycznie wykrywa `ADMIN_API_TOKEN` z pliku `.env`.
- Po utworzeniu/pobraniu klucza demo, automatycznie dodaje funkcję `ai_generate` do planu "Free" (do którego należy klucz demo).
- Zaktualizowano instrukcje/przykłady w skrypcie, aby pokazywały poprawne użycie tokena (`X-Admin-Token`) zamiast Basic Auth.

### 5. Dokumentacja (`MANUAL_TESTING_GUIDE.md`)
Zaktualizowano dokumentację testową:
- Dodano sekcję **Scenario 5: Subscription Plan Management (Admin)**.
- Zaktualizowano tabelę endpointów w sekcji **Health & Admin**.
- Poprawiono informacje o autoryzacji admina (Token zamiast Basic Auth).
