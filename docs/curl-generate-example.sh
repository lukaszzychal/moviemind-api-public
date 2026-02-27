#!/bin/sh
# Przykład wywołania POST /api/v1/generate (bez progress, poprawny JSON dla jq).
# Zastąp YOUR_API_KEY swoim kluczem (z panelu admina lub po php artisan db:seed – pełna wartość pokazywana tylko raz).

# Jedna linia – unikasz błędów "URL rejected" i "jq parse error":
curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -d '{"entity_type":"MOVIE","slug":"the-matrix-1999","locale":"en-US","context_tag":"modern"}' | jq

# Albo bez jq (sam curl):
# curl -s -X POST "http://localhost:8000/api/v1/generate" \
#   -H "Content-Type: application/json" \
#   -H "X-API-Key: TWOJ_KLUCZ" \
#   -d '{"entity_type":"MOVIE","slug":"the-matrix-1999","locale":"en-US","context_tag":"modern"}'
