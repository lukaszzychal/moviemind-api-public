# GitHub Container Registry (GHCR) - Przewodnik

## 📦 Co to jest GHCR?

GitHub Container Registry to prywatne/publliczne repozytorium Docker images zarządzane przez GitHub. Integruje się z GitHub Actions i jest dostępne z poziomu UI GitHub.

## 🔍 Jak Zobaczyć Obrazy Docker w GitHub UI

### 1. **Przez stronę repozytorium**

1. Przejdź do swojego repozytorium na GitHub
2. W górnym menu kliknij **"Packages"** (lub **"Code" → "Packages"**)
3. Zobaczysz listę wszystkich opublikowanych pakietów
4. Kliknij na pakiet, aby zobaczyć szczegóły, wersje, i inne informacje

### 2. **Bezpośredni link**

```
https://github.com/users/lukaszzychal/packages/container/package/moviemind-api-public
```

Lub:

```
https://github.com/lukaszzychal?tab=packages
```

### 3. **Przez Settings → Packages**

1. Kliknij swój avatar (prawy górny róg)
2. Settings
3. Packages
4. Zobaczysz wszystkie pakiety z wszystkich repozytoriów

## 🎯 Co Możesz Zobaczyć w UI

### Informacje dostępne:

- ✅ **Lista wszystkich wersji/tagów** obrazu
- ✅ **Metadata** (autor, data utworzenia, rozmiar)
- ✅ **README** (jeśli dodany)
- ✅ **Download stats** (ile razy pobrano)
- ✅ **Security vulnerabilities** (Trivy scan results)
- ✅ **Delete/Permissions** management

### Tagi obrazów:

W Twoim przypadku obrazy są tagowane jako:
```
ghcr.io/lukaszzychal/moviemind-api-public:sha-<commit-sha>
```

Przykład:
```
ghcr.io/lukaszzychal/moviemind-api-public:sha-4399000
```

## 🔧 Konfiguracja w CI Workflow

### Obecna konfiguracja (z `.github/workflows/ci.yml`):

```yaml
docker-build:
  needs: [test, security]
  name: Docker Build
  if: github.event_name == 'push'
  runs-on: ubuntu-latest
  permissions:
    contents: read
    packages: write  # ← Uprawnienie do zapisu do GHCR
  steps:
    - name: Login to GHCR
      uses: docker/login-action@v3
      with:
        registry: ghcr.io
        username: ${{ github.actor }}
        password: ${{ secrets.GITHUB_TOKEN }}  # ← Automatyczny token
    
    - name: Build and Push
      uses: docker/build-push-action@v6
      with:
        push: ${{ github.event_name == 'push' }}
        tags: |
          ghcr.io/${{ github.repository }}:sha-${{ github.sha }}
```

### Co się dzieje:

1. **Na każdy push** do `main` workflow buduje obraz
2. **Taguje** obraz jako `sha-<commit-hash>`
3. **Pushuje** do `ghcr.io/lukaszzychal/moviemind-api-public`
4. **Obraz jest dostępny** publicznie (jeśli repo publiczne)

## 📥 Jak Pobrać Obraz

### 1. **Publiczny obraz** (jeśli repo publiczne):

```bash
docker pull ghcr.io/lukaszzychal/moviemind-api-public:sha-4399000
```

### 2. **Prywatny obraz** (wymaga logowania):

```bash
# Login do GHCR
echo $GITHUB_TOKEN | docker login ghcr.io -u USERNAME --password-stdin

# Pull obraz
docker pull ghcr.io/lukaszzychal/moviemind-api-public:sha-4399000
```

### 3. **Użycie obrazu lokalnie**:

```bash
docker run -it \
  -e APP_ENV=local \
  -e DB_CONNECTION=pgsql \
  ghcr.io/lukaszzychal/moviemind-api-public:sha-4399000
```

## 🔒 Permissions (Uprawnienia)

### Domyślnie:

- **Publiczny repo** = **Publiczny obraz** (każdy może pull)
- **Prywatny repo** = **Prywatny obraz** (tylko z dostępem)

### Zmiana permissions:

1. Idź do: `https://github.com/users/lukaszzychal/packages/container/package/moviemind-api-public`
2. Kliknij **"Package settings"**
3. W sekcji **"Danger Zone"** możesz:
   - Zmienić na **public/private**
   - Usunąć pakiet
   - Zarządzać uprawnieniami użytkowników

## 🏷️ Lepsze Tagowanie (Rekomendacja)

### Obecne tagowanie:

```yaml
tags: |
  ghcr.io/${{ github.repository }}:sha-${{ github.sha }}
```

### Ulepszone tagowanie (możesz dodać):

```yaml
tags: |
  ghcr.io/${{ github.repository }}:sha-${{ github.sha }}
  ghcr.io/${{ github.repository }}:latest
  ghcr.io/${{ github.repository }}:${{ github.ref_name }}
  ghcr.io/${{ github.repository }}:v${{ github.run_number }}
```

To daje więcej opcji:
- `:latest` - zawsze najnowszy build
- `:main` - najnowszy build z main
- `:v123` - konkretny numer builda

## 📊 Sprawdzanie Obrazów przez CLI

### 1. **Sprawdź dostępne tagi**:

```bash
# Użyj GitHub API
curl -H "Authorization: Bearer $GITHUB_TOKEN" \
  https://api.github.com/orgs/lukaszzychal/packages/container/moviemind-api-public/versions
```

### 2. **Lista obrazów** (wymaga uprawnień):

```bash
# Przez Docker Hub API (GHCR używa podobnego)
curl -u USERNAME:$GITHUB_TOKEN \
  https://ghcr.io/v2/lukaszzychal/moviemind-api-public/tags/list
```

## 🚀 Automatyczne Usuwanie Starych Obrazów

### GitHub Actions workflow do cleanup:

```yaml
name: Cleanup Old Images

on:
  schedule:
    - cron: '0 0 * * 0'  # Co tydzień
  workflow_dispatch:

jobs:
  cleanup:
    runs-on: ubuntu-latest
    permissions:
      packages: write
      contents: read
    steps:
      - name: Delete old images
        uses: actions/delete-package-versions@v4
        with:
          package-name: 'moviemind-api-public'
          package-type: 'container'
          min-versions-to-keep: 10  # Zostaw ostatnie 10
          delete-only-untagged-versions: 'true'
```

## 🔍 Sprawdzenie w CI Pipeline

### Aktualnie obrazy są pushowane gdy:

```yaml
if: github.event_name == 'push'  # Tylko na push, nie na PR
push: ${{ github.event_name == 'push' && (github.ref == 'refs/heads/main' || startsWith(github.ref, 'refs/tags/')) }}
```

To znaczy:
- ✅ Push do `main` → obraz jest buildowany i pushowany
- ✅ Push tagów `v*` → obraz jest buildowany i pushowany
- ❌ Pull Request → obraz jest tylko buildowany (test), nie pushowany

## 📝 Przykładowe Użycie

### 1. **Deploy z GHCR**:

```yaml
# W workflow deploy
- name: Deploy from GHCR
  run: |
    docker pull ghcr.io/lukaszzychal/moviemind-api-public:sha-${{ github.sha }}
    docker-compose up -d
```

### 2. **Lokalne testowanie**:

```bash
# Pobierz najnowszy obraz
docker pull ghcr.io/lukaszzychal/moviemind-api-public:latest

# Uruchom lokalnie
docker run -p 8000:80 \
  -e DB_HOST=localhost \
  ghcr.io/lukaszzychal/moviemind-api-public:latest
```

## ✅ Checklist: Co Masz Teraz

- ✅ Docker build w CI
- ✅ Push do GHCR na każde push do main
- ✅ Tagowanie jako `sha-<commit-hash>`
- ✅ Uprawnienia `packages: write` w workflow
- ⚠️ Brak UI sprawdzania (ale możesz przez Packages tab)

## 🎯 Szybkie Sprawdzenie

1. **Otwórz**: `https://github.com/lukaszzychal?tab=packages`
2. **Lub**: Idź do repo → Packages (w górnym menu)
3. **Sprawdź**: Obrazy są listowane jako `moviemind-api-public`

Wszystkie obrazy buildowane przez CI są tam dostępne! 🎉

