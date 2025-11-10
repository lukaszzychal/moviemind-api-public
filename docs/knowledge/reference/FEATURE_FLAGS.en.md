# Feature Flags – configuration and operations

> **Created:** 2025-11-10  
> **Context:** Centralising Pennant feature-flag configuration and hardening the admin endpoints.  
> **Category:** reference

## 🎯 Purpose

Document the new approach to feature-flag management in MovieMind API: the consolidated `config/pennant.php`, feature metadata and guarded toggle endpoints.

## 📋 Contents

### `config/pennant.php` layout

- `flags` – dictionary of flag definitions:
  - `class` – bound `App\Features\*` class.
  - `description` – message exposed in API/GUI.
  - `category` – logical grouping (core_ai, moderation, i18n, …).
  - `default` – default state consumed by `BaseFeature`.
  - `togglable` – whether the admin API may switch the flag.
- `features` – list passed to Pennant (mapped from `flags`).
- `default` / `stores` – standard Pennant storage configuration (database / array).

### Hardened admin API

- `GET /api/v1/admin/flags` now exposes `category`, `default`, `togglable`.
- `POST /api/v1/admin/flags/{name}`:
  - returns `404` for unknown flags,
  - returns `403` when `togglable === false`.
- `GET /api/v1/admin/flags/usage` only reports flags defined in the configuration.

### Feature classes integration

- New base class `App\Features\BaseFeature` reads defaults from configuration (SnakeCase class name → `flags` key).
- All classes in `app/Features/*` extend `BaseFeature`, so changing default state is a pure config update.

### Flag overview (excerpt)

| Flag                     | Category    | Default | Toggle via API |
|--------------------------|-------------|---------|----------------|
| ai_description_generation | core_ai     | true    | yes            |
| ai_bio_generation         | core_ai     | true    | yes            |
| human_moderation_required | moderation  | false   | yes            |
| public_jobs_polling       | public_api  | true    | yes            |
| (others)                  | mixed       | varies  | no             |

Refer to `config/pennant.php` for the full list and descriptions.

## 🔗 Related Documents

- [TASK_018_FEATURE_FLAGS.en.md](../../tasks/TASK_018_FEATURE_FLAGS.en.md)
- [docs/openapi.yaml](../../openapi.yaml) – updated response schemas

## 📌 Notes

- When adding a new flag, extend `config/pennant.php` (description, category, togglable) and update API/Postman docs if needed.
- If a flag should be managed via the admin API, set `togglable: true` and cover it with tests.

---

**Last updated:** 2025-11-10

