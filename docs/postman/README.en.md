# Postman – MovieMind API

*Polish version: [README.md](./README.md)*

## 📦 Contents
- `moviemind-api.postman_collection.json` – main request collection
- `environments/local.postman_environment.json` – local environment template
- `environments/staging.postman_environment.json` – staging environment template

## 🚀 Importing in Postman
1. Open Postman and choose **Import → File**.
2. Select `docs/postman/moviemind-api.postman_collection.json`.
3. In the **Environments** tab import the desired template (`local` or `staging`).
4. Duplicate the environment file locally (e.g. `local.postman_environment.private.json`) and fill in your real secrets. Do not commit private files.
5. Activate the environment, run the collection, and confirm that `baseUrl` points to the correct API instance.

## ✅ Tests and variables
- Every request includes built-in tests that validate the HTTP status code and basic JSON structure.
- Key values (e.g. `movieSlug`, `jobId`, `movieDescriptionId`, `personBioId`) are written to collection variables so subsequent requests can reuse them.
- `movieDescriptionId` / `personBioId` keep track of baseline description/bio IDs and allow you to call variants via `description_id` / `bio_id`.
- To reset the state, clear the collection variables (**Collections → Variables** panel).

## 🎯 Description and bio variants
- The new **Get movie by slug (selected description)** and **Get person by slug (selected bio)** requests showcase the `description_id` / `bio_id` query parameters.
- Run one of the generation requests first (`Generate movie/person (existing slug -> 202)`) to capture the baseline IDs in collection variables.
- Then call the `GET` variant with the parameter – the response exposes `selected_description` or `selected_bio` for the requested variant.

## 🧪 Running with Newman
```bash
newman run docs/postman/moviemind-api.postman_collection.json \
  -e docs/postman/environments/local.postman_environment.json \
  --reporters cli
```
For staging swap the environment path or provide your own file with secrets.

## 🔐 Sensitive data
- Environment templates only contain placeholders (`{{ADMIN_API_KEY}}`).
- Store real keys in private files ignored by Git.
- Never commit `.postman_environment.json` files with secrets.

