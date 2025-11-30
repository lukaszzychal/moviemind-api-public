#!/bin/bash

# Script do analizy plików SARIF z Trivy
# Użycie: ./scripts/analyze-trivy-sarif.sh <path-to-sarif-file>

set -e

if [ $# -eq 0 ]; then
    echo "❌ Błąd: Podaj ścieżkę do pliku SARIF"
    echo "Użycie: $0 <path-to-sarif-file>"
    exit 1
fi

SARIF_FILE="$1"

if [ ! -f "$SARIF_FILE" ]; then
    echo "❌ Błąd: Plik '$SARIF_FILE' nie istnieje"
    exit 1
fi

if ! command -v jq &> /dev/null; then
    echo "❌ Błąd: jq nie jest zainstalowane"
    echo "Zainstaluj: brew install jq"
    exit 1
fi

echo "📊 Analiza pliku SARIF: $SARIF_FILE"
echo ""

# Liczba wszystkich podatności
TOTAL=$(jq '.runs[0].results | length' "$SARIF_FILE")
echo "📈 Statystyki:"
echo "   Wszystkie podatności: $TOTAL"

# Liczba CRITICAL/HIGH
CRITICAL_HIGH=$(jq '[.runs[0].results[] | select(.level == "error")] | length' "$SARIF_FILE")
echo "   CRITICAL/HIGH: $CRITICAL_HIGH"

# Liczba MEDIUM
MEDIUM=$(jq '[.runs[0].results[] | select(.level == "warning")] | length' "$SARIF_FILE")
echo "   MEDIUM: $MEDIUM"

# Liczba LOW
LOW=$(jq '[.runs[0].results[] | select(.level == "note")] | length' "$SARIF_FILE")
echo "   LOW: $LOW"

echo ""
echo "🔍 Lista CVE (CRITICAL/HIGH):"
jq -r '.runs[0].results[] | select(.level == "error") | .ruleId' "$SARIF_FILE" | sort | uniq | head -20

echo ""
echo "📋 Szczegóły podatności CRITICAL/HIGH:"
jq -r '.runs[0].results[] | select(.level == "error") | "\(.ruleId) | \(.message.text) | \(.locations[0].physicalLocation.artifactLocation.uri // "N/A")"' "$SARIF_FILE" | head -10

