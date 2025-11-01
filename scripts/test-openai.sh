#!/bin/bash
#
# Script do testowania integracji OpenAI API
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🤖 Testowanie OpenAI API Integration${NC}"
echo ""

# Sprawdź czy jesteśmy w katalogu projektu
if [ ! -d "api" ]; then
    echo -e "${RED}❌ Błąd: Uruchom script z katalogu głównego projektu${NC}"
    exit 1
fi

# Sprawdź czy .env istnieje
if [ ! -f "api/.env" ]; then
    echo -e "${YELLOW}⚠️  Plik api/.env nie istnieje${NC}"
    echo -e "${YELLOW}Tworzenie z env/local.env.example...${NC}"
    cp env/local.env.example api/.env
    echo -e "${GREEN}✅ Plik .env utworzony${NC}"
    echo -e "${YELLOW}⚠️  Pamiętaj aby dodać OPENAI_API_KEY do api/.env${NC}"
fi

# Sprawdź czy OpenAI API key jest skonfigurowany
if ! grep -q "OPENAI_API_KEY=sk-" api/.env 2>/dev/null; then
    echo -e "${RED}❌ OPENAI_API_KEY nie jest skonfigurowany w api/.env${NC}"
    echo ""
    echo -e "${YELLOW}Jak uzyskać API key:${NC}"
    echo "  1. Przejdź na: https://platform.openai.com/api-keys"
    echo "  2. Utwórz nowy klucz"
    echo "  3. Dodaj do api/.env: OPENAI_API_KEY=sk-..."
    echo ""
    exit 1
fi

# Sprawdź czy AI_SERVICE=real
if ! grep -q "AI_SERVICE=real" api/.env 2>/dev/null; then
    echo -e "${YELLOW}⚠️  AI_SERVICE nie jest ustawiony na 'real'${NC}"
    echo -e "${YELLOW}Zmieniam na 'real'...${NC}"
    sed -i.bak 's/AI_SERVICE=.*/AI_SERVICE=real/' api/.env
    echo -e "${GREEN}✅ AI_SERVICE=real ustawione${NC}"
fi

echo -e "${GREEN}✅ Konfiguracja sprawdzona${NC}"
echo ""

# Sprawdź czy Docker działa
if ! docker-compose ps | grep -q "Up"; then
    echo -e "${YELLOW}⚠️  Docker containers nie są uruchomione${NC}"
    echo -e "${YELLOW}Uruchamianie...${NC}"
    docker-compose up -d
    sleep 5
fi

echo -e "${BLUE}📋 Informacje o konfiguracji:${NC}"
echo ""
docker-compose exec -T php php artisan tinker --execute="
    echo 'API Key: ' . (config('services.openai.api_key') ? '✅ Skonfigurowany' : '❌ Brak');
    echo 'Model: ' . config('services.openai.model');
    echo 'AI Service: ' . config('services.ai.service');
    echo '';
"

echo ""
echo -e "${BLUE}🧪 Test 1: Bezpośrednie wywołanie OpenAiClient${NC}"
echo ""

docker-compose exec -T php php artisan tinker --execute="
    try {
        \$client = app(\App\Services\OpenAiClientInterface::class);
        echo 'Wywołuję OpenAI API dla filmu \"test-movie\"...' . PHP_EOL;
        \$response = \$client->generateMovie('test-movie');
        
        if (\$response['success'] ?? false) {
            echo '✅ Sukces!' . PHP_EOL;
            echo 'Title: ' . (\$response['title'] ?? 'N/A') . PHP_EOL;
            echo 'Model: ' . (\$response['model'] ?? 'N/A') . PHP_EOL;
        } else {
            echo '❌ Błąd: ' . (\$response['error'] ?? 'Unknown error') . PHP_EOL;
        }
    } catch (\Exception \$e) {
        echo '❌ Exception: ' . \$e->getMessage() . PHP_EOL;
    }
"

echo ""
echo -e "${BLUE}🧪 Test 2: Przez API Endpoint${NC}"
echo ""
echo -e "${YELLOW}Wyślij request:${NC}"
echo "curl -X POST http://localhost:8000/api/v1/generate \\"
echo "  -H 'Content-Type: application/json' \\"
echo "  -d '{\"entity_type\": \"MOVIE\", \"entity_id\": \"test-movie-2\"}'"
echo ""

# Sprawdź czy endpoint jest dostępny
if curl -s http://localhost:8000/api/v1/movies > /dev/null 2>&1; then
    echo -e "${GREEN}✅ API endpoint dostępny${NC}"
else
    echo -e "${RED}❌ API endpoint nie odpowiada${NC}"
    echo -e "${YELLOW}Sprawdź czy serwer działa: docker-compose logs php${NC}"
fi

echo ""
echo -e "${BLUE}📊 Status Queue${NC}"
echo ""

# Sprawdź czy Horizon lub queue:work działa
if docker-compose ps | grep -q "horizon.*Up"; then
    echo -e "${GREEN}✅ Horizon uruchomiony${NC}"
elif docker-compose ps | grep -q "queue.*Up"; then
    echo -e "${GREEN}✅ Queue worker uruchomiony${NC}"
else
    echo -e "${YELLOW}⚠️  Queue worker nie jest uruchomiony${NC}"
    echo -e "${YELLOW}Uruchom: docker-compose up -d horizon${NC}"
    echo -e "${YELLOW}Lub: docker-compose exec php php artisan queue:work${NC}"
fi

echo ""
echo -e "${GREEN}✅ Testy zakończone${NC}"
echo ""
echo -e "${BLUE}📝 Przydatne komendy:${NC}"
echo "  - Sprawdź logi: docker-compose logs -f php"
echo "  - Sprawdź queue: docker-compose logs -f horizon"
echo "  - Sprawdź job status: curl http://localhost:8000/api/v1/jobs/{job_id}"
echo "  - Wyłącz real AI: zmień AI_SERVICE=mock w api/.env"
echo ""

