#!/bin/bash
#
# Skrypt przygotowujący środowisko Docker do realnych testów lokalnych
# 
# Wykonuje:
# 1. Sprawdza czy Docker działa
# 2. Uruchamia kontenery Docker (jeśli nie działają)
# 3. Instaluje zależności i konfiguruje aplikację
# 4. Czyści bazę danych (migrate:fresh)
# 5. Włącza potrzebne flagi funkcji przez API
#
# Użycie:
#   ./scripts/setup-local-testing.sh [opcje]
#
# Opcje:
#   -h, --help          Wyświetl pomoc
#   --api-url URL       URL bazy API (domyślnie: http://localhost:8000)
#   --ai-service MODE   Tryb AI: 'mock' (domyślnie) lub 'real'
#   --no-start          Nie uruchamiaj kontenerów (zakłada że już działają)
#   --rebuild           Rebuild kontenerów przed uruchomieniem
#   --seed              Załaduj testowe dane (fixtures) po migracji
#
# Zmienne środowiskowe:
#   API_BASE_URL        URL bazy API (domyślnie: http://localhost:8000)
#   ADMIN_AUTH          Dane autoryzacji w formacie "user:password" (opcjonalne)
#   DOCKER_COMPOSE_CMD  Komenda docker compose (domyślnie: docker compose)
#   AI_SERVICE          Tryb AI: 'mock' lub 'real' (można też użyć --ai-service)
#   LOAD_FIXTURES       Załaduj dane testowe: 'true' lub 'false' (można też użyć --seed)
#
# Przykłady:
#   ./scripts/setup-local-testing.sh
#   ./scripts/setup-local-testing.sh --seed
#   ./scripts/setup-local-testing.sh --ai-service real --seed
#   ./scripts/setup-local-testing.sh --rebuild --seed
#   API_BASE_URL=http://localhost:8000 ADMIN_AUTH="admin:secret" ./scripts/setup-local-testing.sh
#   LOAD_FIXTURES=true ./scripts/setup-local-testing.sh
#
# Bezpieczeństwo:
#   - Skrypt działa TYLKO w środowisku lokalnym (APP_ENV=local)
#   - Automatycznie sprawdza środowisko przed uruchomieniem
#   - Wymuszenie: FORCE_LOCAL=true ./scripts/setup-local-testing.sh (NIEZALECANE)
#
# Szczegółowe instrukcje: scripts/README.md

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
API_BASE_URL="${API_BASE_URL:-http://localhost:8000}"
API_ENDPOINT="${API_BASE_URL}/api/v1"
ADMIN_ENDPOINT="${API_ENDPOINT}/admin"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
API_DIR="${PROJECT_ROOT}/api"
DOCKER_COMPOSE_CMD="${DOCKER_COMPOSE_CMD:-docker compose}"
DOCKER_CONTAINER_PHP="moviemind-php"
DOCKER_CONTAINER_NGINX="moviemind-nginx"
AI_SERVICE_MODE="${AI_SERVICE:-mock}"  # Will be set by --ai-service option
LOAD_FIXTURES="${LOAD_FIXTURES:-false}"  # Will be set by --seed option

# Feature flags to enable
REQUIRED_FLAGS=(
    "ai_description_generation"
    "ai_bio_generation"
    "tmdb_verification"
    "debug_endpoints"
)

# Functions
print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_header() {
    echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"
}

# Check if Docker is installed and running
check_docker() {
    print_info "Sprawdzanie czy Docker jest zainstalowany i działa..."
    
    if ! command -v docker &> /dev/null; then
        print_error "Docker nie jest zainstalowany"
        print_warning "Zainstaluj Docker: https://docs.docker.com/get-docker/"
        return 1
    fi
    
    if ! docker info > /dev/null 2>&1; then
        print_error "Docker nie działa"
        print_warning "Uruchom Docker Desktop lub docker daemon"
        return 1
    fi
    
    print_success "Docker działa"
    return 0
}

# Check if Docker Compose is available
check_docker_compose() {
    print_info "Sprawdzanie Docker Compose..."
    
    if ! $DOCKER_COMPOSE_CMD version > /dev/null 2>&1; then
        print_error "Docker Compose nie jest dostępne"
        print_warning "Sprawdź czy 'docker compose' działa"
        return 1
    fi
    
    print_success "Docker Compose dostępne"
    return 0
}

# Check if containers are running
check_containers() {
    print_info "Sprawdzanie czy kontenery są uruchomione..."
    
    if $DOCKER_COMPOSE_CMD ps --services --filter "status=running" 2>/dev/null | grep -q "php\|nginx"; then
        print_success "Kontenery Docker działają"
        return 0
    else
        print_warning "Kontenery Docker nie są uruchomione"
        return 1
    fi
}

# Start Docker containers
start_docker_containers() {
    print_info "Uruchamianie kontenerów Docker..."
    
    cd "$PROJECT_ROOT"
    
    if [ ! -f "docker-compose.yml" ]; then
        print_error "Plik docker-compose.yml nie istnieje w ${PROJECT_ROOT}"
        return 1
    fi
    
    # Check if .env exists
    if [ ! -f "${API_DIR}/.env" ]; then
        print_warning "Plik .env nie istnieje, kopiuję szablon..."
        if [ -f "${PROJECT_ROOT}/env/local.env.example" ]; then
            cp "${PROJECT_ROOT}/env/local.env.example" "${API_DIR}/.env"
            print_success "Utworzono plik .env z szablonu"
        else
            print_error "Nie znaleziono szablonu .env"
            return 1
        fi
    fi
    
    # Update .env with AI_SERVICE if needed
    if grep -q "^AI_SERVICE=" "${API_DIR}/.env" 2>/dev/null; then
        # Update existing AI_SERVICE
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS
            sed -i '' "s/^AI_SERVICE=.*/AI_SERVICE=${AI_SERVICE_MODE}/" "${API_DIR}/.env"
        else
            # Linux
            sed -i "s/^AI_SERVICE=.*/AI_SERVICE=${AI_SERVICE_MODE}/" "${API_DIR}/.env"
        fi
    else
        # Add AI_SERVICE if not exists
        echo "AI_SERVICE=${AI_SERVICE_MODE}" >> "${API_DIR}/.env"
    fi
    
    print_info "Tryb AI: ${AI_SERVICE_MODE}"
    
    # Check if OPENAI_API_KEY is needed for real mode
    if [ "$AI_SERVICE_MODE" = "real" ]; then
        if ! grep -q "^OPENAI_API_KEY=.*[^=]$" "${API_DIR}/.env" 2>/dev/null || grep -q "^OPENAI_API_KEY=$" "${API_DIR}/.env" 2>/dev/null || grep -q "^OPENAI_API_KEY=sk-REPLACE_ME" "${API_DIR}/.env" 2>/dev/null; then
            print_warning "Tryb REAL wymaga OPENAI_API_KEY"
            print_info "Ustaw OPENAI_API_KEY w ${API_DIR}/.env lub użyj zmiennej środowiskowej"
        fi
    fi
    
    # Export AI_SERVICE for docker-compose
    export AI_SERVICE="$AI_SERVICE_MODE"
    
    print_info "Uruchamianie: $DOCKER_COMPOSE_CMD up -d --build"
    if $DOCKER_COMPOSE_CMD up -d --build; then
        print_success "Kontenery Docker uruchomione"
        
        # Wait for services to be ready
        print_info "Oczekiwanie na gotowość serwisów..."
        sleep 5
        
        return 0
    else
        print_error "Nie udało się uruchomić kontenerów Docker"
        return 1
    fi
}

# Setup Laravel application in Docker
setup_laravel() {
    print_info "Konfigurowanie aplikacji Laravel w Dockerze..."
    
    # Install Composer dependencies
    print_info "Instalowanie zależności Composer..."
    if $DOCKER_COMPOSE_CMD exec -T php composer install --no-interaction; then
        print_success "Zależności Composer zainstalowane"
    else
        print_warning "Nie udało się zainstalować zależności (może już są zainstalowane)"
    fi
    
    # Generate application key if needed
    print_info "Sprawdzanie klucza aplikacji..."
    if $DOCKER_COMPOSE_CMD exec -T php php artisan key:generate --force 2>/dev/null; then
        print_success "Klucz aplikacji wygenerowany"
    else
        print_info "Klucz aplikacji już istnieje"
    fi
    
    return 0
}

# Check if API is running
check_api_health() {
    print_info "Sprawdzanie czy API działa..."
    
    local max_attempts=30
    local attempt=0
    
    while [ $attempt -lt $max_attempts ]; do
        if curl -s -f "${API_BASE_URL}/api/v1/health/openai" > /dev/null 2>&1; then
            print_success "API działa"
            return 0
        fi
        
        attempt=$((attempt + 1))
        if [ $attempt -lt $max_attempts ]; then
            print_info "Oczekiwanie na API... ($attempt/$max_attempts)"
            sleep 2
        fi
    done
    
    print_error "API nie odpowiada na ${API_BASE_URL}"
    print_warning "Sprawdź logi: $DOCKER_COMPOSE_CMD logs nginx"
    return 1
}

# Check if admin auth is required
check_admin_auth() {
    print_info "Sprawdzanie czy autoryzacja admin jest wymagana..."
    
    # Try without auth first
    response=$(curl -s -w "\n%{http_code}" "${ADMIN_ENDPOINT}/flags" 2>/dev/null || echo "")
    http_code=$(echo "$response" | tail -n1)
    
    if [ "$http_code" = "200" ]; then
        print_success "Autoryzacja nie jest wymagana (środowisko lokalne)"
        return 1  # Auth not required
    elif [ "$http_code" = "401" ] || [ "$http_code" = "403" ]; then
        print_warning "Autoryzacja jest wymagana"
        return 0  # Auth required
    else
        print_warning "Nie można określić statusu autoryzacji (HTTP $http_code)"
        return 0  # Assume auth required to be safe
    fi
}

# Enable feature flag
enable_flag() {
    local flag_name=$1
    local auth_required=$2
    local admin_token=$3
    
    print_info "Włączanie flagi: ${flag_name}..."
    
    local curl_cmd="curl -s -w \"\\n%{http_code}\" -X POST \"${ADMIN_ENDPOINT}/flags/${flag_name}\" \
        -H \"Content-Type: application/json\" \
        -d '{\"state\": \"on\"}'"
    
    if [ "$auth_required" = "1" ]; then
        if [ -z "$admin_token" ]; then
            print_error "Autoryzacja wymagana, ale nie podano tokena admina"
            print_warning "Ustaw ADMIN_API_TOKEN w .env"
            return 1
        fi
        curl_cmd="${curl_cmd} -H \"X-Admin-Token: ${admin_token}\""
    fi
    
    response=$(eval "$curl_cmd" 2>/dev/null || echo "")
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    if [ "$http_code" = "200" ]; then
        print_success "Flaga ${flag_name} włączona"
        return 0
    elif [ "$http_code" = "404" ]; then
        print_warning "Flaga ${flag_name} nie istnieje (może być tylko do odczytu)"
        return 0
    elif [ "$http_code" = "403" ]; then
        print_warning "Flaga ${flag_name} nie może być zmieniona przez API (togglable: false)"
        return 0
    else
        print_error "Nie udało się włączyć flagi ${flag_name} (HTTP $http_code)"
        echo "$body" | jq '.' 2>/dev/null || echo "$body"
        return 1
    fi
}

# Reset database
reset_database() {
    print_info "Czyszczenie bazy danych w Dockerze..."
    
    print_info "Uruchamianie: docker compose exec php php artisan migrate:fresh"
    if $DOCKER_COMPOSE_CMD exec -T php php artisan migrate:fresh --force; then
        print_success "Baza danych wyczyszczona i migracje uruchomione"
        
        # Load test fixtures (seeders) if --seed option is provided
        if [ "$LOAD_FIXTURES" = "true" ]; then
            print_info "Ładowanie przykładowych danych testowych (seeders)..."
            
            # Capture output to extract API Key
            seeder_output=$($DOCKER_COMPOSE_CMD exec -T php php artisan db:seed --force)
            exit_code=$?
            
            # Print output to console (streaming/buffered)
            echo "$seeder_output"
            
            if [ $exit_code -eq 0 ]; then
                print_success "Przykładowe dane załadowane"
                print_info "Załadowane dane:"
                print_info "  • Filmy: The Matrix (1999), Inception (2010)"
                print_info "  • Osoby: Keanu Reeves, The Wachowskis, Christopher Nolan"
                print_info "  • Gatunki: Action, Sci-Fi, Thriller"
                
                # Extract API Key
                DEMO_API_KEY=$(echo "$seeder_output" | grep -o "Plaintext key: [a-zA-Z0-9_-]*" | head -n 1 | awk '{print $3}')
                if [ -n "$DEMO_API_KEY" ]; then
                    print_success "Przechwycono domyślny klucz API: $DEMO_API_KEY"
                fi
            else
                print_warning "Nie udało się załadować przykładowych danych (seeders)"
                print_warning "Możesz załadować je ręcznie: docker compose exec php php artisan db:seed"
            fi
        else
            print_info "Pomijam ładowanie danych testowych (użyj --seed aby załadować)"
        fi
        return 0
    else
        print_error "Nie udało się wyczyścić bazy danych"
        print_warning "Sprawdź logi: $DOCKER_COMPOSE_CMD logs php"
        return 1
    fi
}

# Show help
show_help() {
    echo "Skrypt przygotowujący środowisko Docker do realnych testów lokalnych"
    echo ""
    echo "Użycie:"
    echo "  $0 [opcje]"
    echo ""
    echo "Opcje:"
    echo "  -h, --help          Wyświetl tę pomoc"
    echo "  --api-url URL       URL bazy API (domyślnie: http://localhost:8000)"
    echo "  --ai-service MODE    Tryb AI: 'mock' (domyślnie) lub 'real'"
    echo "  --no-start          Nie uruchamiaj kontenerów (zakłada że już działają)"
    echo "  --rebuild           Rebuild kontenerów przed uruchomieniem"
    echo "  --seed              Załaduj testowe dane (fixtures) po migracji"
    echo ""
    echo "Zmienne środowiskowe:"
    echo "  API_BASE_URL        URL bazy API (domyślnie: http://localhost:8000)"
    echo "  ADMIN_AUTH          Dane autoryzacji w formacie \"user:password\""
    echo "  DOCKER_COMPOSE_CMD  Komenda docker compose (domyślnie: docker compose)"
    echo "  AI_SERVICE          Tryb AI: 'mock' lub 'real' (można też użyć --ai-service)"
    echo "  LOAD_FIXTURES       Załaduj dane testowe: 'true' lub 'false' (można też użyć --seed)"
    echo ""
    echo "Przykład:"
    echo "  $0                                    # Tryb mock (domyślnie), bez danych testowych"
    echo "  $0 --seed                             # Załaduj dane testowe po migracji"
    echo "  $0 --ai-service real --seed           # Tryb real z OpenAI + dane testowe"
    echo "  $0 --ai-service mock --rebuild --seed # Rebuild z trybem mock + dane testowe"
    echo "  API_BASE_URL=http://localhost:8000 $0"
    echo "  ADMIN_AUTH=\"admin:secret\" $0"
    echo "  LOAD_FIXTURES=true $0                 # Załaduj dane testowe (zmienna środowiskowa)"
    echo ""
    echo "Uwaga:"
    echo "  - Tryb 'real' wymaga ustawienia OPENAI_API_KEY w .env"
    echo "  - Tryb 'mock' nie wymaga klucza OpenAI (używa deterministycznych danych)"
    echo "  - Skrypt działa TYLKO w środowisku lokalnym (APP_ENV=local)"
    echo ""
    echo "Bezpieczeństwo:"
    echo "  - Skrypt sprawdza środowisko przed uruchomieniem"
    echo "  - Wymuszenie uruchomienia: FORCE_LOCAL=true $0"
    echo ""
    exit 0
}

# Parse arguments
NO_START=false
REBUILD=false

while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            show_help
            ;;
        --api-url)
            if [ -z "$2" ]; then
                print_error "Opcja --api-url wymaga wartości URL"
                exit 1
            fi
            API_BASE_URL="$2"
            API_ENDPOINT="${API_BASE_URL}/api/v1"
            ADMIN_ENDPOINT="${API_ENDPOINT}/admin"
            shift 2
            continue
            ;;
        --ai-service)
            if [ -z "$2" ] || ([ "$2" != "mock" ] && [ "$2" != "real" ]); then
                print_error "Nieprawidłowy tryb AI: $2 (dozwolone: mock, real)"
                echo "Użycie: --ai-service mock  lub  --ai-service real"
                exit 1
            fi
            AI_SERVICE_MODE="$2"
            shift 2
            continue
            ;;
        --no-start)
            NO_START=true
            ;;
        --rebuild)
            REBUILD=true
            ;;
        --seed)
            LOAD_FIXTURES=true
            ;;
        *)
            print_error "Nieznana opcja: $1"
            echo "Użyj --help aby zobaczyć dostępne opcje"
            exit 1
            ;;
    esac
    shift
done

# Check if running in local environment
check_local_environment() {
    print_info "Sprawdzanie środowiska..."
    
    local is_local=true
    local warnings=()
    
    # Check 1: APP_ENV from .env file
    if [ -f "${API_DIR}/.env" ]; then
        local app_env=$(grep "^APP_ENV=" "${API_DIR}/.env" 2>/dev/null | cut -d '=' -f2 | tr -d '"' | tr -d "'" | tr -d ' ' || echo "")
        if [ -n "$app_env" ] && [ "$app_env" != "local" ] && [ "$app_env" != "testing" ]; then
            print_error "APP_ENV w .env jest ustawiony na: ${app_env}"
            print_error "Skrypt może być uruchomiony tylko w środowisku lokalnym (APP_ENV=local)"
            warnings+=("APP_ENV=${app_env} (wymagane: local)")
            is_local=false
        fi
    fi
    
    # Check 2: API URL should be localhost
    if [[ ! "$API_BASE_URL" =~ ^http://(localhost|127\.0\.0\.1)(:[0-9]+)?$ ]]; then
        print_warning "API_BASE_URL wskazuje na: ${API_BASE_URL}"
        print_warning "Oczekiwany URL lokalny: http://localhost:8000"
        warnings+=("API_BASE_URL=${API_BASE_URL} (może być niebezpieczne)")
    fi
    
    # Check 3: Hostname check (heuristic)
    local hostname=$(hostname 2>/dev/null || echo "")
    if [ -n "$hostname" ]; then
        # Check for production-like hostnames
        if [[ "$hostname" =~ (prod|production|staging|live|server|aws|azure|gcp|railway|heroku) ]]; then
            print_warning "Hostname wygląda na środowisko produkcyjne: ${hostname}"
            warnings+=("Hostname=${hostname} (może być produkcja)")
        fi
    fi
    
    # Check 4: Environment variable override
    if [ -n "$FORCE_LOCAL" ] && [ "$FORCE_LOCAL" = "true" ]; then
        print_warning "FORCE_LOCAL=true - pomijam sprawdzanie środowiska"
        return 0
    fi
    
    # Final check
    if [ "$is_local" = false ]; then
        print_error ""
        print_error "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        print_error "⚠️  BEZPIECZEŃSTWO: Skrypt wykrył środowisko inne niż lokalne!"
        print_error "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        print_error ""
        print_error "Ten skrypt może być uruchomiony TYLKO w środowisku lokalnym."
        print_error ""
        print_error "Wykryte problemy:"
        for warning in "${warnings[@]}"; do
            print_error "  • $warning"
        done
        print_error ""
        print_error "Aby wymusić uruchomienie (NIEZALECANE), ustaw:"
        print_error "  export FORCE_LOCAL=true"
        print_error ""
        print_error "UWAGA: Uruchomienie tego skryptu w produkcji może:"
        print_error "  • Wyczyścić bazę danych produkcyjną"
        print_error "  • Zmienić konfigurację środowiska"
        print_error "  • Spowodować przestoje"
        print_error ""
        return 1
    fi
    
    if [ ${#warnings[@]} -gt 0 ]; then
        print_warning "Wykryto potencjalne problemy, ale kontynuuję..."
        for warning in "${warnings[@]}"; do
            print_warning "  • $warning"
        done
    else
        print_success "Środowisko lokalne potwierdzone"
    fi
    
    return 0
}

# Main execution
main() {
    print_header "🐳 Przygotowanie środowiska Docker do testów lokalnych - MovieMind API"
    
    # Step 0: Check local environment (SECURITY)
    print_header "🔒 Sprawdzanie bezpieczeństwa środowiska"
    if ! check_local_environment; then
        exit 1
    fi
    
    # Step 1: Check Docker
    print_header "🐳 Sprawdzanie Docker"
    if ! check_docker; then
        exit 1
    fi
    
    if ! check_docker_compose; then
        exit 1
    fi
    
    # Step 2: Check/Start containers
    print_header "🚀 Kontenery Docker"
    
    # Show AI service mode
    print_info "Tryb AI: ${AI_SERVICE_MODE}"
    if [ "$AI_SERVICE_MODE" = "real" ]; then
        print_warning "Tryb REAL - upewnij się, że OPENAI_API_KEY jest ustawiony w .env"
    fi
    
    if [ "$NO_START" = false ]; then
        if ! check_containers; then
            if [ "$REBUILD" = true ]; then
                print_info "Rebuild kontenerów..."
                cd "$PROJECT_ROOT"
                export AI_SERVICE="$AI_SERVICE_MODE"
                $DOCKER_COMPOSE_CMD build --no-cache
            fi
            if ! start_docker_containers; then
                exit 1
            fi
        else
            print_info "Kontenery już działają"
            
            # Update AI_SERVICE if changed
            if [ -f "${API_DIR}/.env" ]; then
                current_ai_service=$(grep "^AI_SERVICE=" "${API_DIR}/.env" 2>/dev/null | cut -d '=' -f2 || echo "mock")
                if [ "$current_ai_service" != "$AI_SERVICE_MODE" ]; then
                    print_info "Zmiana trybu AI z ${current_ai_service} na ${AI_SERVICE_MODE}..."
                    if [[ "$OSTYPE" == "darwin"* ]]; then
                        sed -i '' "s/^AI_SERVICE=.*/AI_SERVICE=${AI_SERVICE_MODE}/" "${API_DIR}/.env"
                    else
                        sed -i "s/^AI_SERVICE=.*/AI_SERVICE=${AI_SERVICE_MODE}/" "${API_DIR}/.env"
                    fi
                    export AI_SERVICE="$AI_SERVICE_MODE"
                    print_info "Restartowanie kontenerów z nowym trybem AI..."
                    cd "$PROJECT_ROOT"
                    $DOCKER_COMPOSE_CMD up -d --force-recreate php horizon
                    sleep 3
                fi
            fi
        fi
    else
        print_info "Pomijam uruchamianie kontenerów (--no-start)"
        if ! check_containers; then
            print_error "Kontenery nie działają, ale użyto --no-start"
            exit 1
        fi
    fi
    
    # Step 3: Setup Laravel
    print_header "⚙️  Konfiguracja Laravel"
    if ! setup_laravel; then
        print_warning "Niektóre kroki konfiguracji mogły się nie powieść"
    fi
    
    # Step 4: Check API health
    print_header "🏥 Sprawdzanie API"
    if ! check_api_health; then
        print_warning "API nie odpowiada, ale kontynuuję..."
    fi
    
    # Step 5: Reset database
    print_header "🗄️  Czyszczenie bazy danych"
    if ! reset_database; then
        exit 1
    fi
    
    # Step 6: Check admin auth
    print_header "🔐 Konfiguracja autoryzacji"
    auth_required=0
    admin_token=""
    
    if check_admin_auth; then
        auth_required=1
        # Try to get token from .env
        if [ -f "${API_DIR}/.env" ]; then
            # Extract ADMIN_API_TOKEN from .env
            admin_token=$(grep "^ADMIN_API_TOKEN=" "${API_DIR}/.env" 2>/dev/null | cut -d '=' -f2- | sed 's/#.*//' | tr -d '"' | tr -d "'" | xargs)
            
            if [ -n "$admin_token" ]; then
                print_info "Używam tokena admina z .env (${admin_token:0:8}***)"
            fi
        fi
        
        if [ -z "$admin_token" ]; then
            print_warning "Autoryzacja wymagana, ale nie znaleziono tokena admina"
            print_info "Dodaj ADMIN_API_TOKEN do ${API_DIR}/.env"
            print_warning "Próbuję kontynuować bez autoryzacji..."
        fi
    fi
    
    # Step 7: Enable feature flags
    print_header "🚩 Włączanie flag funkcji"
    
    success_count=0
    skip_count=0
    error_count=0
    
    for flag in "${REQUIRED_FLAGS[@]}"; do
        if enable_flag "$flag" "$auth_required" "$admin_token"; then
            if [ $? -eq 0 ]; then
                ((success_count++))
            else
                ((skip_count++))
            fi
        else
            ((error_count++))
        fi
    done
    
    # Step 8: Summary
    print_header "📊 Podsumowanie"
    
    print_success "Kontenery Docker: uruchomione"
    print_success "Tryb AI: ${AI_SERVICE_MODE}"
    print_success "Baza danych: wyczyszczona i gotowa"
    if [ "$LOAD_FIXTURES" = "true" ]; then
        print_success "Przykładowe dane: załadowane (seeders)"
    else
        print_info "Przykładowe dane: pominięte (użyj --seed aby załadować)"
    fi
    print_info "Flagi włączone: ${success_count}"
    print_info "Flagi pominięte (tylko do odczytu): ${skip_count}"
    
    if [ $error_count -gt 0 ]; then
        print_warning "Błędy podczas włączania flag: ${error_count}"
    fi

    echo ""
    print_header "🔑 Dane Dostępowe"
    
    if [ -n "$DEMO_API_KEY" ]; then
        print_success "Twoj Klucz API: ${DEMO_API_KEY}"
        export DEMO_API_KEY
        
        # Auto-enable features for demo key's plan (Free)
        if [ -f "${API_DIR}/.env" ]; then
            ADMIN_TOKEN=$(grep "^ADMIN_API_TOKEN=" "${API_DIR}/.env" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" | xargs)
            if [ -n "$ADMIN_TOKEN" ]; then
                print_info "Konfigurowanie funkcji subskrypcji..."
                
                # Get Free plan ID
                FREE_PLAN_ID=$(curl -s -H "X-Admin-Token: $ADMIN_TOKEN" "${API_ENDPOINT}/admin/subscription-plans" | jq -r '.data[] | select(.name=="free") | .id')
                
                if [ -n "$FREE_PLAN_ID" ] && [ "$FREE_PLAN_ID" != "null" ]; then
                    print_info "Znaleziono plan Free: $FREE_PLAN_ID. Dodaję funkcję ai_generate..."
                    curl -s -X POST "${API_ENDPOINT}/admin/subscription-plans/${FREE_PLAN_ID}/features" \
                        -H "X-Admin-Token: $ADMIN_TOKEN" \
                        -H "Content-Type: application/json" \
                        -d '{"feature":"ai_generate"}' > /dev/null
                    print_success "Funkcja ai_generate dodana do planu Free (dla klucza demo)"
                else
                    print_warning "Nie znaleziono planu Free. Pomijam konfigurację funkcji."
                fi
            else
                print_warning "Brak tokena admina (ADMIN_API_TOKEN). Pomijam konfigurację funkcji subskrypcji."
            fi
        fi
    else
        if [ "$LOAD_FIXTURES" = "true" ]; then
            print_warning "Nie udało się przechwycić klucza API z logów."
        else
            print_warning "Brak klucza API (użyj --seed aby wygenerować demo)"
        fi
        DEMO_API_KEY="<TWOJ_KLUCZ>"
    fi
    
    print_header "✅ Środowisko Docker gotowe do testów!"
    
    echo ""
    print_info "Możesz teraz testować API. Skopiuj poniższe polecenie aby ustawić klucz API w terminalu:"
    echo -e "${GREEN}export API_KEY=\"${DEMO_API_KEY}\"${NC}"
    echo ""
    
    print_info "Przykładowe dane dostępne w bazie:"
    echo "  • Filmy: The Matrix (1999), Inception (2010)"
    echo "  • Osoby: Keanu Reeves, The Wachowskis, Christopher Nolan"
    echo ""
    print_info "Sprawdź status flag:"
    # Extract ADMIN_API_TOKEN for display
    DISPLAY_ADMIN_TOKEN=$(grep "^ADMIN_API_TOKEN=" "${API_DIR}/.env" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" | xargs)
    echo "  • curl -s -H \"X-Admin-Token: ${DISPLAY_ADMIN_TOKEN:-<TOKEN>}\" ${ADMIN_ENDPOINT}/flags | jq"
    echo ""
    print_info "Przydatne komendy Docker:"
    echo "  • Logi: $DOCKER_COMPOSE_CMD logs -f"
    echo "  • Status: $DOCKER_COMPOSE_CMD ps"
    echo "  • Zatrzymanie: $DOCKER_COMPOSE_CMD down"
    echo ""
    
    # API Endpoints Reference
    print_header "📚 API Endpoints Reference (z użyciem jq)"
    echo "Większość endpointów publicznych wymaga nagłówka X-API-Key."
    echo ""
    
    echo -e "${YELLOW}📽️  MOVIES${NC}"
    echo -e "${GREEN}GET${NC}  ${API_ENDPOINT}/movies"
    echo "  Description: List all movies (with optional ?q=search query)"
    echo "  Example: curl -s -H \"X-API-Key: \$API_KEY\" \"${API_ENDPOINT}/movies?q=Matrix\" | jq"
    echo ""
    
    echo -e "${GREEN}GET${NC}  ${API_ENDPOINT}/movies/search"
    echo "  Description: Advanced movie search (local + TMDb)"
    echo "  Query params: ?q=title&year=1999&director=Name&actor[]=Name1&actor[]=Name2&page=1&per_page=20"
    echo "  Example: curl -s -H \"X-API-Key: \$API_KEY\" \"${API_ENDPOINT}/movies/search?q=Matrix&year=1999&page=1&per_page=10\" | jq"
    echo ""
    
    echo -e "${GREEN}GET${NC}  ${API_ENDPOINT}/movies/{slug}"
    echo "  Description: Get movie by slug (may return disambiguation if ambiguous)"
    echo "  Query params: ?description_id=123 (optional)"
    echo "  Example: curl -s -H \"X-API-Key: \$API_KEY\" ${API_ENDPOINT}/movies/the-matrix-1999 | jq"
    echo ""
    
    echo -e "${GREEN}POST${NC} ${API_ENDPOINT}/movies/{slug}/refresh"
    echo "  Description: Refresh movie metadata from TMDb"
    echo "  Example: curl -s -H \"X-API-Key: \$API_KEY\" -X POST ${API_ENDPOINT}/movies/the-matrix-1999/refresh | jq"
    echo ""
    
    echo -e "${YELLOW}👤 PEOPLE${NC}"
    echo -e "${GREEN}GET${NC}  ${API_ENDPOINT}/people"
    echo "  Description: List all people (with optional ?q=search query)"
    echo "  Example: curl -s -H \"X-API-Key: \$API_KEY\" \"${API_ENDPOINT}/people?q=Keanu\" | jq"
    echo ""
    
    echo -e "${GREEN}GET${NC}  ${API_ENDPOINT}/people/{slug}"
    echo "  Description: Get person by slug"
    echo "  Query params: ?bio_id=123 (optional)"
    echo "  Example: curl -s -H \"X-API-Key: \$API_KEY\" ${API_ENDPOINT}/people/keanu-reeves | jq"
    echo ""
    
    echo -e "${GREEN}POST${NC} ${API_ENDPOINT}/people/{slug}/refresh"
    echo "  Description: Refresh person metadata from TMDb"
    echo "  Example: curl -s -H \"X-API-Key: \$API_KEY\" -X POST ${API_ENDPOINT}/people/keanu-reeves/refresh | jq"
    echo ""
    
    echo -e "${YELLOW}🤖 GENERATION${NC}"
    echo -e "${GREEN}POST${NC} ${API_ENDPOINT}/generate"
    echo "  Description: Queue AI description/bio generation"
    echo "  Required body: {\"entity_type\":\"MOVIE\",\"slug\":\"the-matrix-1999\"}"
    echo "  Optional body fields: \"locale\":\"en-US\", \"context_tag\":\"DEFAULT\""
    echo "  entity_type: MOVIE | ACTOR | PERSON"
    echo "  context_tag: DEFAULT | modern | critical | humorous"
    echo "  Example: curl -s -X POST \"${API_ENDPOINT}/generate\" \\"
    echo "    -H \"X-API-Key: \$API_KEY\" \\"
    echo "    -H \"Content-Type: application/json\" \\"
    echo "    -d '{\"entity_type\":\"MOVIE\",\"slug\":\"the-matrix-1999\",\"locale\":\"en-US\",\"context_tag\":\"DEFAULT\"}' | jq"
    echo ""
    
    echo -e "${YELLOW}📋 JOBS${NC}"
    echo -e "${GREEN}GET${NC}  ${API_ENDPOINT}/jobs/{id}"
    echo "  Description: Get generation job status"
    echo "  Example: curl -s -H \"X-API-Key: \$API_KEY\" ${API_ENDPOINT}/jobs/7bec7007-7e93-4db5-afe4-0a96c490a16d | jq"
    echo ""
    
    echo -e "${YELLOW}🏥 HEALTH${NC}"
    echo -e "${GREEN}GET${NC}  ${API_ENDPOINT}/health/openai"
    echo "  Description: Check OpenAI API connectivity"
    echo "  Example: curl -s \"${API_ENDPOINT}/health/openai\" | jq"
    echo ""
    
    echo -e "${YELLOW}🔐 ADMIN (requires Admin Token)${NC}"
    echo -e "${GREEN}GET${NC}  ${ADMIN_ENDPOINT}/flags"
    echo "  Description: List all feature flags"
    echo "  Example: curl -s -H \"X-Admin-Token: \$ADMIN_API_TOKEN\" ${ADMIN_ENDPOINT}/flags | jq"
    echo ""
    
    echo -e "${GREEN}POST${NC} ${ADMIN_ENDPOINT}/flags/{name}"
    echo "  Description: Set feature flag state"
    echo "  Body: {\"state\":\"on\"} or {\"state\":\"off\"}"
    echo "  Example: curl -s -X POST ${ADMIN_ENDPOINT}/flags/ai_description_generation \\"
    echo "    -H \"X-Admin-Token: \$ADMIN_API_TOKEN\" \\"
    echo "    -H \"Content-Type: application/json\" \\"
    echo "    -d '{\"state\":\"on\"}' | jq"
    echo ""
    
    echo -e "${GREEN}GET${NC}  ${ADMIN_ENDPOINT}/flags/usage"
    echo "  Description: Get feature flags usage statistics"
    echo "  Example: curl -s -H \"X-Admin-Token: \$ADMIN_API_TOKEN\" ${ADMIN_ENDPOINT}/flags/usage | jq"
    echo ""
    
    echo -e "${GREEN}GET${NC}  ${ADMIN_ENDPOINT}/debug/config"
    echo "  Description: Get debug configuration (development only)"
    echo "  Example: curl -s -H \"X-Admin-Token: \$ADMIN_API_TOKEN\" ${ADMIN_ENDPOINT}/debug/config | jq"
    echo ""
    
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo -e "${YELLOW}Response Codes:${NC}"
    echo "  200 - Success"
    echo "  202 - Generation queued (check job status)"
    echo "  300 - Disambiguation required (multiple matches)"
    echo "  404 - Not found"
    echo "  422 - Validation error"
    echo "  500 - Server error"
    echo ""
}

# Run main function
main

