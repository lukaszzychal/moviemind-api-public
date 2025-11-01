#!/bin/bash
#
# Script do czyszczenia bazy danych i włączania flag
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🗑️  Czyszczenie bazy danych i włączanie flag${NC}"
echo ""

# Sprawdź czy Docker działa
if ! docker-compose ps | grep -q "php.*Up"; then
    echo -e "${RED}❌ Docker containers nie są uruchomione${NC}"
    echo -e "${YELLOW}Uruchom: docker-compose up -d${NC}"
    exit 1
fi

echo -e "${YELLOW}1️⃣  Czyszczenie bazy danych (bez seedów)...${NC}"
docker-compose exec -T php php artisan migrate:fresh --force

echo ""
echo -e "${YELLOW}2️⃣  Włączanie flag AI...${NC}"
docker-compose exec -T php php artisan tinker --execute="
Laravel\Pennant\Feature::activate('ai_description_generation');
Laravel\Pennant\Feature::activate('ai_bio_generation');
echo '✅ Flagi aktywowane' . PHP_EOL;
"

echo ""
echo -e "${YELLOW}3️⃣  Sprawdzanie statusu flag...${NC}"
docker-compose exec -T php php artisan tinker --execute="
echo 'ai_description_generation: ' . (Laravel\Pennant\Feature::active('ai_description_generation') ? '✅ ON' : '❌ OFF') . PHP_EOL;
echo 'ai_bio_generation: ' . (Laravel\Pennant\Feature::active('ai_bio_generation') ? '✅ ON' : '❌ OFF') . PHP_EOL;
"

echo ""
echo -e "${GREEN}✅ Gotowe!${NC}"
echo ""
echo -e "${BLUE}📋 Możesz teraz:${NC}"
echo "  • Testować generowanie filmów: POST /api/v1/generate"
echo "  • Sprawdzić status flag: GET /api/v1/admin/flags"
echo "  • Włączyć/wyłączyć flagi: POST /api/v1/admin/flags/{name}"
echo ""

