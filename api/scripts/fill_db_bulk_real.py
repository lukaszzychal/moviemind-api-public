
import requests
import time
import sys
import random

# Konfiguracja
BASE_URL = "https://moviemind-api-staging.up.railway.app/api/v1"
API_KEY = "mm_demo_portfolio_staging"  # Twój klucz API (możesz zmienić)

# Opcje generowania zgodne z App\Enums\ContextTag.php i App\Enums\Locale.php
LOCALES = ["pl-PL", "en-US", "de-DE", "fr-FR", "es-ES"]
CONTEXT_TAGS = ["DEFAULT", "modern", "critical", "humorous"]

REAL_MOVIES = [
    "dune-part-two-2024", "inception-2010", "the-matrix-1999", "interstellar-2014", "pulp-fiction-1994",
    "the-godfather-1972", "gladiator-2000", "avatar-2009", "titanic-1997", "joker-2019",
    "oppenheimer-2023", "barbie-2023", "the-dark-knight-2008", "parasite-2019", "shutter-island-2010",
    "the-wolf-of-wall-street-2013", "se7en-1995", "fight-club-1999", "forrest-gump-1994", "goodfellas-1990"
]

REAL_PEOPLE = [
    "tom-hanks", "leonardo-dicaprio", "christopher-nolan", "steven-spielberg", "quentin-tarantino",
    "meryl-streep", "brad-pitt", "scarlett-johansson", "robert-downey-jr", "johnny-depp",
    "morgan-freeman", "denzel-washington", "tom-cruise", "natalie-portman", "christian-bale",
    "joaquin-phoenix", "anne-hathaway", "matthew-mcconaughey", "emma-stone", "ryan-gosling"
]

REAL_SERIES = [
    "breaking-bad-2008", "stranger-things-2016", "game-of-thrones-2011", "the-sopranos-1999", "the-wire-2002",
    "friends-1994", "the-office-2005", "better-call-saul-2015", "succession-2018", "the-crown-2016",
    "the-last-of-us-2023", "black-mirror-2011", "chernobyl-2019", "narcos-2015", "ted-lasso-2020",
    "the-boys-2019", "mandalorian-2019", "the-bear-2022", "yellowstone-2018", "dark-2017"
]

REAL_SHOWS = [
    "the-tonight-show-1954", "the-daily-show-1996", "saturday-night-live-1975", "the-simpsons-1989", "jeopardy-1964",
    "sesame-street-1969", "top-gear-2002", "mythbusters-2003", "south-park-1997", "family-guy-1999",
    "masterchef-2010", "shark-tank-2009", "survivor-2000", "the-voice-2011", "american-idol-2002",
    "hells-kitchen-2005", "the-bachelor-2002", "pawn-stars-2009", "gold-rush-2010", "dirty-jobs-2005"
]

def send_generate_request(entity_type, slug, locale, context_tag):
    url = f"{BASE_URL}/generate"
    headers = {
        "X-API-Key": API_KEY,
        "Accept": "application/json",
        "Content-Type": "application/json"
    }
    payload = {
        "entity_type": entity_type,
        "slug": slug,
        "locale": locale,
        "context_tag": context_tag
    }
    
    try:
        response = requests.post(url, json=payload, headers=headers)
        return response
    except Exception as e:
        print(f"Error: {e}")
        return None

def main():
    if API_KEY == "YOUR_API_KEY_HERE":
        print("BŁĄD: Musisz podać API_KEY w skrypcie!")
        sys.exit(1)

    print(f"Rozpoczynanie generowania realnych encji na: {BASE_URL}")
    print("-" * 50)

    total_sent = 0
    total_success = 0
    total_rate_limited = 0

    categories = [
        {"type": "MOVIE", "items": REAL_MOVIES},
        {"type": "PERSON", "items": REAL_PEOPLE},
        {"type": "TV_SERIES", "items": REAL_SERIES},
        {"type": "TV_SHOW", "items": REAL_SHOWS}
    ]

    # Liczba próbek per kategoria
    FOR_EACH_CATEGORY = 100

    for cat in categories:
        print(f"Przetwarzanie kategorii: {cat['type']}")
        for i in range(FOR_EACH_CATEGORY):
            item_slug = cat['items'][i % len(cat['items'])]
            locale = random.choice(LOCALES)
            context_tag = random.choice(CONTEXT_TAGS)
            
            while True:
                response = send_generate_request(cat['type'], item_slug, locale, context_tag)
                total_sent += 1
                
                if response is not None:
                    if response.status_code == 202:
                        total_success += 1
                        print(f"[{total_sent}/{4*FOR_EACH_CATEGORY}] {cat['type']}: {item_slug} ({locale}, {context_tag}) -> 202 Accepted")
                        break # Następny request
                    elif response.status_code == 429:
                        total_rate_limited += 1
                        retry_after = int(response.headers.get("Retry-After", 60))
                        print(f"!! RATE LIMITED [{total_sent}] !! Odczekuję {retry_after}s...")
                        time.sleep(retry_after)
                        # Retry w pętli while
                    else:
                        print(f"[{total_sent}] {cat['type']}: {item_slug} -> Error {response.status_code}: {response.text}")
                        break
                else:
                    break
            
            # Przerwa między requestami, żeby nie bić w limity za mocno
            time.sleep(0.1)

    print("-" * 50)
    print("ZAKOŃCZONO")
    print(f"Wysłano łącznie prób: {total_sent}")
    print(f"Sukces (202): {total_success}")
    print(f"Rate Limited (429): {total_rate_limited}")

if __name__ == "__main__":
    main()
