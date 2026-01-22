# Rozwiązanie Problemu Przekierowań w Środowisku Docker + Nginx + Laravel

**Data:** 2026-01-12
**Autor:** AI Assistant

---

### Problem

Aplikacja Laravel (w szczególności panel Filament) działająca w kontenerze Docker za reverse proxy (Nginx) generuje nieprawidłowe URL-e przekierowań po zalogowaniu. Przekierowanie następuje na adres bez zewnętrznego portu (np. na `http://localhost/admin` zamiast `http://localhost:8000/admin`), co powoduje błąd `ERR_CONNECTION_REFUSED`.

### Przyczyna

Problem ma dwa główne źródła, które nakładają się na siebie w środowisku Docker:

1.  **Nginx i Przekierowania 301:** Domyślnie, gdy Nginx otrzymuje żądanie do ścieżki, która wygląda jak katalog, ale nie ma na końcu ukośnika (np. `/admin`), może próbować "naprawić" URL, wykonując przekierowanie 301 na `/admin/`. Jeśli w tym procesie nie przekaże poprawnie informacji o porcie, przeglądarka otrzyma i **zapamięta na stałe** błędny URL bez portu.
2.  **Brak świadomości Laravela o Proxy:** Aplikacja Laravel, działając wewnątrz kontenera, "widzi" żądanie przychodzące na wewnętrzny port 80. Nie ma pojęcia o mapowaniu portów w Dockerze (np. `8000:80`) i generuje URL-e na podstawie tego, co sama widzi, gubiąc zewnętrzny port.

### Ostateczne Rozwiązanie

Rozwiązanie wymaga konfiguracji na trzech poziomach: **Nginx**, **Middleware Laravela** oraz **ServiceProvider Laravela**, aby zapewnić spójną i poprawną komunikację.

#### 1. Konfiguracja Nginx (`docker/nginx/default.conf`)

To jest najważniejszy element naprawy. Konfiguracja musi jawnie informować PHP-FPM o oryginalnym hoście i porcie oraz zapobiegać niechcianym przekierowaniom.

```nginx
server {
    listen 80;
    server_name localhost;

    root /var/www/html/public;
    index index.php index.html;

    # Kluczowe: Zapobiega automatycznym przekierowaniom Nginx (np. z /admin na /admin/)
    # To chroni przed cachowaniem błędnych przekierowań 301 przez przeglądarkę.
    absolute_redirect off;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Jawna obsługa ścieżki /admin, aby uniknąć traktowania jej jako katalog
    location = /admin {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass php:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        
        # Kluczowe: Przekazanie poprawnych nagłówków do PHP-FPM
        # Dzięki temu Laravel wie, że działa za proxy.
        fastcgi_param HOST $http_host;
        fastcgi_param SERVER_PORT $server_port;
        fastcgi_param REMOTE_ADDR $remote_addr;
    }
    
    # ... reszta konfiguracji
}
```

#### 2. Konfiguracja Zaufanego Proxy w Laravel (`api/bootstrap/app.php`)

Laravel musi wiedzieć, że ma ufać nagłówkom (`X-Forwarded-*`) przesyłanym przez Nginx.

```php
// api/bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    // Ufaj wszystkim proxy (kluczowe dla środowiska Docker)
    $middleware->trustProxies(at: '*');
    
    // ... reszta middleware
})
```

#### 3. Wymuszenie Głównego URL w Laravel (`api/app/Providers/AppServiceProvider.php`)

To dodatkowe zabezpieczenie, które zapewnia, że URL-e generowane w tle (np. w jobach, konsoli) są zawsze poprawne, bazując na `APP_URL` z pliku `.env`.

```php
// api/app/Providers/AppServiceProvider.php
public function boot(): void
{
    // Wymuś poprawny URL, jeśli jest zdefiniowany w .env
    if (config('app.url')) {
        URL::forceRootUrl(config('app.url'));
    }
}
```

### Kroki do wykonania po wprowadzeniu zmian

1.  **Wyczyść cache przeglądarki:** To absolutnie kluczowe, ponieważ przeglądarka mogła zapamiętać błędne przekierowanie 301. Najlepiej testować w trybie incognito.
2.  **Przebuduj kontener Nginx:** Zmiany w `default.conf` wymagają przebudowy.
    ```sh
    docker compose up -d --build --force-recreate nginx
    ```
3.  **Wyczyść cache Laravela (opcjonalnie, ale zalecane):**
    ```sh
    docker compose exec php php artisan route:clear
    docker compose exec php php artisan config:clear
    ```

Po wykonaniu tych kroków problem z przekierowaniem powinien zostać trwale rozwiązany.
