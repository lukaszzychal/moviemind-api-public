# OpenAI Responses API vs Chat Completions

> **Data utworzenia:** 2025-11-10  
> **Kontekst:** Migracja klienta OpenAI do unified Responses API w MovieMind API  
> **Kategoria:** technical

## 🎯 Cel

Wytłumaczyć różnice między endpointami `chat/completions` oraz `responses`, a także uzasadnić zmiany w konfiguracji projektu.

## 📋 Zawartość

1. **Nowy domyślny endpoint**  
   - `OpenAiClient` korzysta teraz z `https://api.openai.com/v1/responses` (Responses API).  
   - Zapewnia natywne wsparcie dla `response_format.type=json_schema`, dzięki czemu otrzymujemy walidowany JSON.

2. **Format żądań**  
   - Responses API używa tablicy `input` z blokami `input_text` (system + user).  
   - Wymagany format odpowiedzi definiujemy przez `json_schema` (np. pola filmu/bio).

3. **Obsługa legacy**  
   - Ustawienie `OPENAI_URL=https://api.openai.com/v1/chat/completions` przywraca stary schemat (`messages`).  
   - Klient automatycznie mapuje odpowiedzi JSON niezależnie od użytego endpointu.

4. **Migracja testów i dokumentacji**  
   - Testy jednostkowe (`OpenAiClientTest`) pokrywają oba formaty.  
   - Dokumentacja (`AI_SERVICE_CONFIGURATION`) opisuje nowy domyślny adres.

5. **Fallback & bezpieczeństwo**  
   - `extractContent()` radzi sobie z różnymi typami bloków (`json_schema`, `output_text`).  
   - Logowanie błędów i polityka backoff pozostały bez zmian.

## 🔗 Powiązane Dokumenty

- [AI Service Configuration – Tryb Mock vs Real](./AI_SERVICE_CONFIGURATION.md)
- [OpenAI Responses API vs Chat Completions (EN)](./OPENAI_RESPONSES_API.en.md)

## 📌 Notatki

- W przypadku nowych modeli OpenAI należy zweryfikować kompatybilność `json_schema`.  
- Zachowaliśmy kompatybilność z testowym skryptem `scripts/openai-test.php`, który domyślnie używa Responses API.

---

**Ostatnia aktualizacja:** 2025-11-10

