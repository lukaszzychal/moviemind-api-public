# 📋 Przykłady Response w Insomii

**Data:** 2025-11-01

---

## ❓ Pytanie

**"Czy w Insomii można dodać przykłady response?"**

---

## ✅ Odpowiedź

**Tak, ale z ograniczeniami!**

Insomnia **nie obsługuje** wbudowanych przykładów response w taki sam sposób jak Postman. W Postman możesz mieć wiele przykładów w sekcji `response`, ale Insomnia używa innego formatu.

---

## 🔍 Jak Insomnia obsługuje przykłady?

### **Opcja 1: Response History** ✅ (Najprostsze)

**Insomnia automatycznie zapisuje odpowiedzi:**
1. Wykonaj request
2. Response zostaje zapisany w historii
3. Możesz wrócić do poprzednich odpowiedzi

**Zalety:**
- ✅ Automatyczne
- ✅ Nie wymaga ręcznej konfiguracji

**Wady:**
- ⚠️ Wymaga wykonania requestu
- ⚠️ Nie ma wielu przykładów dla różnych scenariuszy

---

### **Opcja 2: Response Bodies jako dokumentacja** ✅ (Rekomendowane)

**Możesz dodać przykłady w opisie requestu:**

```json
{
  "name": "People - Show",
  "description": "Get person details by slug.\n\n**Returns:**\n- `200 OK` - Person exists\n- `202 Accepted` - Person missing, feature flag enabled\n\n**Example Response (200 OK):**\n```json\n{\n  \"id\": 123,\n  \"slug\": \"christopher-nolan\",\n  \"name\": \"Christopher Nolan\"\n}\n```"
}
```

**Zalety:**
- ✅ Widać w interfejsie Insomii
- ✅ Łatwe do utrzymania
- ✅ Działa jako dokumentacja

**Wady:**
- ⚠️ Nie można "przełączyć" między przykładami jak w Postman

---

### **Opcja 3: Osobne requesty dla różnych scenariuszy** ✅

**Utwórz osobne requesty:**
- `People - Show (200 OK)`
- `People - Show (202 Accepted)`
- `People - Show (404 Not Found)`

**Zalety:**
- ✅ Możesz zobaczyć różne scenariusze
- ✅ Łatwe do testowania

**Wady:**
- ⚠️ Duplikacja requestów
- ⚠️ Więcej requestów w kolekcji

---

### **Opcja 4: Użyj OpenAPI** ✅ (Najlepsze dla dokumentacji)

**Insomnia może importować OpenAPI:**
1. Utwórz `openapi.yaml` z przykładami
2. Importuj do Insomii
3. Przykłady są dostępne jako dokumentacja

**Zalety:**
- ✅ Standaryzowany format
- ✅ Łatwe do utrzymania
- ✅ Działa w wielu narzędziach

**Wady:**
- ⚠️ OpenAPI nie obsługuje wielu przykładów dla jednego status code (tylko `examples` w OAS 3.0+)

---

## 📊 Porównanie Postman vs Insomnia

| Funkcja | Postman | Insomnia |
|---------|---------|----------|
| **Przykłady response** | ✅ Tak (wbudowane) | ⚠️ Ograniczone (tylko historia) |
| **Wiele przykładów** | ✅ Tak | ❌ Nie (tylko ostatnia odpowiedź) |
| **Przełączanie przykładów** | ✅ Tak | ❌ Nie |
| **Dokumentacja w opisach** | ✅ Tak | ✅ Tak |
| **OpenAPI import** | ✅ Tak | ✅ Tak |

---

## 🎯 Rekomendacja dla kolekcji Insomii

**Najlepsze podejście:**

1. ✅ **Dodaj przykłady w opisach** (jak w obecnej kolekcji)
2. ✅ **Użyj zmiennych środowiskowych** dla różnych slugów
3. ✅ **Utwórz osobne requesty** dla różnych scenariuszy (opcjonalnie)

**Przykład (obecna kolekcja):**

```json
{
  "name": "People - Show",
  "description": "Get person details by slug.\n\n**Returns:**\n- `200 OK` - Person exists\n- `202 Accepted` - Person missing, feature flag enabled\n- `404 Not Found` - Person missing, feature flag disabled"
}
```

**Przykładowe response są w opisach, ale nie jako "klikalne" przykłady jak w Postman.**

---

## ✅ Podsumowanie

**Insomnia NIE obsługuje przykładów response tak jak Postman**, ale:

1. ✅ **Możesz dodać przykłady w opisach** - widoczne jako dokumentacja
2. ✅ **Możesz użyć zmiennych** - łatwa zmiana między scenariuszami
3. ✅ **Możesz utworzyć osobne requesty** - dla różnych scenariuszy
4. ✅ **Możesz importować OpenAPI** - dla pełnej dokumentacji

**Obecna kolekcja Insomii używa podejścia z opisami + zmiennymi, co jest najlepszym kompromisem.**

---

**Ostatnia aktualizacja:** 2025-11-01

