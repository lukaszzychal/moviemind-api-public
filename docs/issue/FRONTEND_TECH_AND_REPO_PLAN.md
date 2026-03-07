# Plan: Frontend do MovieMind API – technologia i repo

## 1. Słownik pojęć i skrótów

### SPA (Single Page Application)
Aplikacja działająca w przeglądarce, w której cały interfejs jest ładowany raz (jedna „strona” HTML), a dalsza nawigacja i dane są pobierane dynamicznie (JavaScript, wywołania API). Brak pełnego przeładowania strony – routing i widoki zmieniają się po stronie klienta. Przykłady: Gmail, Notion. **Plusy:** płynne UX, API jako jedyne źródło danych. **Minusy:** SEO wymaga dodatków (SSR lub pre-render), pierwsze ładowanie może być cięższe.

### SSR (Server-Side Rendering)
Renderowanie HTML po stronie serwera dla każdego żądania. Przeglądarka dostaje gotowy HTML (lepiej dla SEO i pierwszego wrażenia), potem JavaScript „ożywia” stronę (hydration). Odpowiednik: klasyczne Laravel + Blade, Next.js/Nuxt w trybie SSR. **Kontrast ze SPA:** SPA renderuje w przeglądarce; SSR – na serwerze. Dla MovieMind na start **SPA jest prostsze** (API już jest, front tylko go konsumuje).

### Vite
Narzędzie do budowania frontendu (dev server + bundler). Szybki cold start i HMR (Hot Module Replacement). Używane domyślnie w Vue 3 i wielu nowych projektach; zastępuje Webpack w nowych setupach. **W tym projekcie:** Vue 3 będzie oparty o Vite (np. `npm create vue@latest` z Vite).

### Headwind UI / DaisyUI
- **Headwind UI** – komercyjna biblioteka komponentów UI (Tailwind Labs), płatna. Gotowe sekcje i komponenty pod Tailwind.
- **DaisyUI** – otwarta biblioteka komponentów (przyciski, karty, navbar itd.) jako klasy Tailwind. Działa z Tailwind; można jej użyć później, jeśli chcesz gotowe komponenty bez pisania wszystkiego od zera.

Oba są **opcjonalne** – start możliwy z samym Tailwindem, DaisyUI można dodać gdy pojawią się powtarzalne bloki UI.

### S3 (Amazon Simple Storage Service)
Obiektowa przechowalnia plików w AWS. Typowe użycie dla frontendu: wrzucenie zbudowanych plików (np. `frontend/dist`) do bucketa S3, a potem serwowanie ich przez CDN (np. CloudFront) lub bezpośrednio. Płacisz za przechowywanie i transfer.

### CloudFront
CDN (Content Delivery Network) w AWS. Serwuje pliki (np. z S3) z serwerów blisko użytkownika, cache’uje odpowiedzi. Typowy flow: build frontendu → upload do S3 → CloudFront serwuje z S3. Przyspiesza ładowanie i odciąża główny serwer.

### Vercel
Platforma do hostingu i CI/CD dla aplikacji frontendowych (React, Vue, Next, Nuxt itd.). Łączy repo z Git (np. GitHub), buduje projekt przy pushu i serwuje statyczne pliki / SSR. Darmowy tier dla projektów open-source i małych aplikacji. **Alternatywa** do własnego Nginx + build na serwerze.

### Netlify
Podobnie jak Vercel – hosting i automatyzacja dla frontendu. Podłączenie repo, build (np. `npm run build`), deploy. Formularze, funkcje serverless, CDN w cenie. **Użycie:** dobry wybór na szybki deploy SPA (Vue + Vite) bez konfiguracji serwera.

---

## 2. Podjęta decyzja

| Aspekt | Decyzja |
|--------|--------|
| **Technologia** | **Vue 3 + Tailwind** (Vite jako build tool). |
| **Lokalizacja** | **Ten sam repozytorium**, katalog **`frontend/`** w głównym katalogu repo. |

Struktura docelowa:

```
moviemind-api-public/
  api/                 # Laravel (bez zmian)
  docker/
  frontend/            # Vue 3 + Vite + Tailwind
    src/
    public/
    package.json
    vite.config.ts
    tailwind.config.js
  docs/
  .github/workflows/
```

- **API** – bez zmian (obecny Docker, staging, deploy).
- **Frontend** – osobny build (`cd frontend && npm ci && npm run build`), artefakt `frontend/dist`; deploy np. Vercel, Netlify, S3+CloudFront, albo ten sam host z Nginx serwującym `dist`.

---

## 3. Kolejne kroki (po zatwierdzeniu)

- Założenie projektu Vue 3 (Vite) + Tailwind w katalogu `frontend/`.
- Konfiguracja zmiennej środowiskowej (np. `VITE_API_BASE_URL`) i proxy do API w dev.
- Opcjonalnie: workflow CI (np. `frontend.yml`) – lint + build.
- Krótki wpis w dokumentacji (np. CLAUDE.md) o strukturze repo i katalogu `frontend/`.
