# Manual scenarios results – 20260221_170315

| Name | Result | Request | Response (status + excerpt) |
|------|--------|---------|-----------------------------|
| Scenario 1: List All Movies | Pass (green) | `GET http://localhost:8000/api/v1/movies` | 200 — `{"data":[{"id":"019c80dc-eefe-7399-b6c0-7728b3dbc1e4","title":"the matrix reloaded pre load","slug":"the-matrix-reloaded-pre-load-2003","release_year":2003,"director":"Mock AI Director","genres":["Sci...` |
| Scenario 1: List Movies with q=matrix | Pass (green) | `GET http://localhost:8000/api/v1/movies?q=matrix` | 200 — `{"data":[{"id":"019c80dc-eefe-7399-b6c0-7728b3dbc1e4","title":"the matrix reloaded pre load","slug":"the-matrix-reloaded-pre-load-2003","release_year":2003,"director":"Mock AI Director","genres":["Sci...` |
| Scenario 2: Search by title (q=matrix) | Pass (green) | `GET http://localhost:8000/api/v1/movies/search?q=matrix` | 200 — `{"results":[{"source":"local","slug":"the-matrix-reloaded-pre-load-2003","title":"the matrix reloaded pre load","release_year":2003,"director":"Mock AI Director","has_description":true},{"source":"ext...` |
| Scenario 2: Search by title and year | Pass (green) | `GET http://localhost:8000/api/v1/movies/search?q=matrix&year=1999` | 200 — `{"results":[{"source":"local","slug":"the-matrix-reloaded-pre-load-2003","title":"the matrix reloaded pre load","release_year":2003,"director":"Mock AI Director","has_description":true},{"source":"ext...` |
| Scenario 2: Search by actor only | Failed (red) | `GET http://localhost:8000/api/v1/movies/search?actor=Keanu%20Reeves` | 404 (expected 200) — `{"error":"No movies found","message":"No movies match your search criteria.","match_type":"none","total":0,"results":[]}...` |
| Scenario 2: Search with pagination | Pass (green) | `GET http://localhost:8000/api/v1/movies/search?q=matrix&page=1&per_page=2` | 200 — `{"results":[{"source":"local","slug":"the-matrix-reloaded-pre-load-2003","title":"the matrix reloaded pre load","release_year":2003,"director":"Mock AI Director","has_description":true},{"source":"ext...` |
| Scenario 2: Search no results (404) | Pass (green) | `GET http://localhost:8000/api/v1/movies/search?q=NonexistentMovieXYZ123` | 404 — `{"error":"No movies found","message":"No movies match your search criteria.","match_type":"none","total":0,"results":[]}...` |
| Scenario 3: Get Movie Details (the-matrix-1999) | Failed (red) | `GET http://localhost:8000/api/v1/movies/the-matrix-1999` | 404 (expected 200) — `{"error":"Movie not found"}...` |
| Scenario 4: Bulk Retrieve (GET slugs) | Pass (green) | `GET http://localhost:8000/api/v1/movies?slugs=the-matrix-1999,inception-2010` | 200 — `{"data":[],"not_found":["the-matrix-1999","inception-2010"]}...` |
| Scenario 4: Bulk with include | Pass (green) | `GET http://localhost:8000/api/v1/movies?slugs=the-matrix-1999&include=descriptions,people,genres` | 200 — `{"data":[],"not_found":["the-matrix-1999"]}...` |
| Scenario 5: Search disambiguation (bad boys) | Pass (green) | `GET http://localhost:8000/api/v1/movies/search?q=bad+boys` | 200 — `{"results":[{"source":"external","title":"Bad Boys","release_year":1995,"director":"Michael Bay","overview":"Marcus Burnett is a henpecked family man. Mike Lowrey is a footloose and fancy free ladies'...` |
| Scenario 5: Get movie by slug (bad-boys-ii-2003) | Failed (red) | `GET http://localhost:8000/api/v1/movies/bad-boys-ii-2003` | 404 (expected 200) — `{"error":"Movie not found"}...` |
| Scenario 6: Refresh Movie (POST) | Failed (red) | `POST http://localhost:8000/api/v1/movies/the-matrix-1999/refresh` | 404 (expected 200) — `{"error":"Movie not found"}...` |
| Scenario 7: Movie Collection | Failed (red) | `GET http://localhost:8000/api/v1/movies/the-matrix-1999/collection` | 404 (expected 200) — `{"error":"Movie not found"}...` |
| Scenario 8: Related movies | Failed (red) | `GET http://localhost:8000/api/v1/movies/the-matrix-1999/related` | 404 (expected 200) — `{"error":"Movie not found"}...` |
| TC-MOVIE-001: List Movies | Pass (green) | `GET http://localhost:8000/api/v1/movies` | 200 — `{"data":[{"id":"019c80dc-eefe-7399-b6c0-7728b3dbc1e4","title":"the matrix reloaded pre load","slug":"the-matrix-reloaded-pre-load-2003","release_year":2003,"director":"Mock AI Director","genres":["Sci...` |
| TC-MOVIE-002: Get Movie by Slug | Failed (red) | `GET http://localhost:8000/api/v1/movies/the-matrix-1999` | 404 (expected 200) — `{"error":"Movie not found"}...` |
| TC-MOVIE-003: Search Movies | Pass (green) | `GET http://localhost:8000/api/v1/movies/search?q=matrix` | 200 — `{"results":[{"source":"local","slug":"the-matrix-reloaded-pre-load-2003","title":"the matrix reloaded pre load","release_year":2003,"director":"Mock AI Director","has_description":true},{"source":"ext...` |
| TC-MOVIE-004: Bulk Retrieve (POST) | Pass (green) | `POST http://localhost:8000/api/v1/movies/bulk | body: {"slugs":["the-matrix-1999","inception-2010"]}` | 200 — `{"data":[],"not_found":["the-matrix-1999","inception-2010"]}...` |
| TC-MOVIE-005: Compare Movies | Failed (red) | `GET http://localhost:8000/api/v1/movies/compare?slug1=the-matrix-1999&slug2=inception-2010` | 404 (expected 200) — `{"error":"Movie not found"}...` |
| TC-MOVIE-006: Get Related Movies | Failed (red) | `GET http://localhost:8000/api/v1/movies/the-matrix-1999/related` | 404 (expected 200) — `{"error":"Movie not found"}...` |
| TC-MOVIE-007: Get Movie Collection | Failed (red) | `GET http://localhost:8000/api/v1/movies/the-matrix-1999/collection` | 404 (expected 200) — `{"error":"Movie not found"}...` |
| TC-MOVIE-008: Refresh Movie Data | Failed (red) | `POST http://localhost:8000/api/v1/movies/the-matrix-1999/refresh` | 404 (expected 200) — `{"error":"Movie not found"}...` |
| TC-MOVIE-009: Report Movie Issue | Failed (red) | `POST http://localhost:8000/api/v1/movies/the-matrix-1999/report | body: {"type":"factual_error","message":"Test report from manual scenarios"}` | 404 (expected 201) — `{"error":"Movie not found"}...` |
| TC-MOVIE-010: Disambiguation (search bad+boys) | Pass (green) | `GET http://localhost:8000/api/v1/movies/search?q=bad+boys` | 200 — `{"results":[{"source":"external","title":"Bad Boys","release_year":1995,"director":"Michael Bay","overview":"Marcus Burnett is a henpecked family man. Mike Lowrey is a footloose and fancy free ladies'...` |
| Scenario Health: Health Check | Pass (green) | `GET http://localhost:8000/api/v1/health` | 200 — `{"status":"healthy","timestamp":"2026-02-21T16:03:18+00:00","checks":{"database":{"status":"ok","message":"Database connection established"},"openai":{"success":true,"message":"OpenAI API reachable","...` |

---
Passed: 14, Failed: 12

## Full request/response per scenario

### Scenario 1: List All Movies — PASS
- **Request:** `GET http://localhost:8000/api/v1/movies`
- **Response status:** 200
- **Response body:**
```json
{"data":[{"id":"019c80dc-eefe-7399-b6c0-7728b3dbc1e4","title":"the matrix reloaded pre load","slug":"the-matrix-reloaded-pre-load-2003","release_year":2003,"director":"Mock AI Director","genres":["Sci-Fi","Action"],"default_description_id":"019c80dc-ef04-7015-94e6-bca073d00f4f","created_at":"2026-02-21T15:41:28.000000Z","updated_at":"2026-02-21T15:41:28.000000Z","descriptions_count":2,"default_description":{"id":"019c80dc-ef04-7015-94e6-bca073d00f4f","locale":"en-US","text":"Generated description for the matrix reloaded pre load (en-US locale). This text was produced by MockGenerateMovieJob (AI_SERVICE=mock).","context_tag":"DEFAULT","origin":"GENERATED","ai_model":"mock-ai-1"},"people":[],"_links":{"self":{"href":"http:\/\/localhost:8000\/api\/v1\/movies\/the-matrix-reloaded-pre-load-2003"},"generate":{"href":"http:\/\/localhost:8000\/api\/v1\/generate","method":"POST","body":{"entity_type":"MOVIE","entity_id":"019c80dc-eefe-7399-b6c0-7728b3dbc1e4"}},"people":[]}}]}
```

### Scenario 1: List Movies with q=matrix — PASS
- **Request:** `GET http://localhost:8000/api/v1/movies?q=matrix`
- **Response status:** 200
- **Response body:**
```json
{"data":[{"id":"019c80dc-eefe-7399-b6c0-7728b3dbc1e4","title":"the matrix reloaded pre load","slug":"the-matrix-reloaded-pre-load-2003","release_year":2003,"director":"Mock AI Director","genres":["Sci-Fi","Action"],"default_description_id":"019c80dc-ef04-7015-94e6-bca073d00f4f","created_at":"2026-02-21T15:41:28.000000Z","updated_at":"2026-02-21T15:41:28.000000Z","descriptions_count":2,"default_description":{"id":"019c80dc-ef04-7015-94e6-bca073d00f4f","locale":"en-US","text":"Generated description for the matrix reloaded pre load (en-US locale). This text was produced by MockGenerateMovieJob (AI_SERVICE=mock).","context_tag":"DEFAULT","origin":"GENERATED","ai_model":"mock-ai-1"},"people":[],"_links":{"self":{"href":"http:\/\/localhost:8000\/api\/v1\/movies\/the-matrix-reloaded-pre-load-2003"},"generate":{"href":"http:\/\/localhost:8000\/api\/v1\/generate","method":"POST","body":{"entity_type":"MOVIE","entity_id":"019c80dc-eefe-7399-b6c0-7728b3dbc1e4"}},"people":[]}}]}
```

### Scenario 2: Search by title (q=matrix) — PASS
- **Request:** `GET http://localhost:8000/api/v1/movies/search?q=matrix`
- **Response status:** 200
- **Response body:**
```json
{"results":[{"source":"local","slug":"the-matrix-reloaded-pre-load-2003","title":"the matrix reloaded pre load","release_year":2003,"director":"Mock AI Director","has_description":true},{"source":"external","title":"Matrix","release_year":1973,"director":"Malcolm Le Grice","overview":"Abstract art film made for gallery exhibition.","needs_creation":true,"suggested_slug":"matrix-1973-malcolm-le-grice"},{"source":"external","title":"Matrix","release_year":1973,"director":"James Cagle","overview":"\"MATRIX is a flicker film which utilizes 81 still photographs of my wife's head. It is a film dependent upon variation of intense light changes by calculated combinations of black and white frame alte","needs_creation":true,"suggested_slug":"matrix-1973-james-cagle"},{"source":"external","title":"Matrix","release_year":1998,"director":"Nicky Hamlyn","overview":"The film is composed of receding planes in a landscape: a back garden and the houses beyond. The wooden lattice fence, visible in the image, marks the border between enclosed and open, private and pub","needs_creation":true,"suggested_slug":"matrix-1998"},{"source":"external","title":"Armitage: Dual Matrix","release_year":2002,"director":"Katsuhito Akiyama","overview":"Naomi Armitage and Ross Sylibus have changed their names and live with their daughter Yoko as a happy and normal family on Mars \u2014 until an android riot breaks out at an anti-matter plant on Earth.","needs_creation":true,"suggested_slug":"armitage-dual-matrix-2002"},{"source":"external","title":"Armitage III: Poly Matrix","release_year":1996,"director":"Takuya Sato","overview":"Ross Sylibus is assigned to a police unit on a Martian colony, to find that women are being murdered by a psychotic named D'anclaude. He is assigned a very unorthodox partner named Naomi Armitage, who","needs_creation":true,"suggested_slug":"armitage-iii-poly-matrix-1996"},{"source":"external","title":"The Matrix","release_year":1999,"director":"Lana Wachowski","overview":"Set in the 22nd century, The Matrix tells the story of a computer hacker who joins a group of underground insurgents fighting the vast and powerful computers who now rule the earth.","needs_creation":true,"suggested_slug":"the-matrix-1999"},{"source":"external","title":"The Matrix: Generation","release_year":2023,"director":"Benjamin Clavel","overview":"After the 1999 premiere of the first Matrix movie, it became a pop culture phenomenon. A special documentary about the Matrix saga and its prophetic aspects.","needs_creation":true,"suggested_slug":"the-matrix-generation-2023"},{"source":"external","title":"The Matrix Reloaded","release_year":2003,"director":"Lilly Wachowski","overview":"The Resistance builds in numbers as humans are freed from the Matrix and brought to the city of Zion. Neo discovers his superpowers, including the ability to see the code inside the Matrix. With machi","needs_creation":true,"suggested_slug":"the-matrix-reloaded-2003"},{"source":"external","title":"The Matrix Resurrections","release_year":2021,"director":"Lana Wachowski","overview":"Plagued by strange memories, Neo's life takes an unexpected turn when he finds himself back inside the Matrix.","needs_creation":true,"suggested_slug":"the-matrix-resurrections-2021"},{"source":"external","title":"The Matrix Revolutions","release_year":2003,"director":"Lilly Wachowski","overview":"The human city of Zion defends itself against the massive invasion of the machines as Neo fights to end the war at another front while also opposing the rogue Agent Smith.","needs_creation":true,"suggested_slug":"the-matrix-revolutions-2003"},{"source":"external","title":"Matrix Dream Maze","release_year":2023,"director":"Guo Wenji","overview":"Legend has it that people who meet the dream demon are trapped there forever.","needs_creation":true,"suggested_slug":"matrix-dream-maze-2023"},{"source":"external","title":"Chess Boxing Matrix","release_year":1988,"director":"Chih-Cheng Wang","overview":"Action packed vampire kung fu movie produced by Joseph Kuo. Jack Long portrays a fighting Taoist priest, who helps with the aid of his disciples to reunite a baby vampire with his parents from the \"Ki","needs_creation":true,"suggested_slug":"chess-boxing-matrix-1988"},{"source":"external","title":"The Matrix","release_year":null,"director":"Pedro Guimar\u00e3es","overview":"\"A Matriz\" is a short documentary about Pra\u00e7a da Matriz, an important square in Porto Alegre. Through images, stories, and reflections, it explores the place\u2019s history, architecture, and role in ev","needs_creation":true,"suggested_slug":"the-matrix"},{"source":"external","title":"180 Years of Matrix Croatica","release_year":2022,"director":"Leon Rizmaul","overview":"A documentary on the first 180 years of Matrix Croatica, the leading organization for promoting, studying, recording and funding all aspects of Croatia's cultural heritage in the Balkans and beyond.","needs_creation":true,"suggested_slug":"180-years-of-matrix-croatica-2022"},{"source":"external","title":"Exit The Matrix","release_year":2019,"director":"Alexandra Prikhodko","overview":"In the Absheron region of the Krasnodar Territory, among the majestic Caucasus Mountains and impenetrable forests, small villages were lost. They are connected to the rest of the world only by a thin ","needs_creation":true,"suggested_slug":"exit-the-matrix-2019"},{"source":"external","title":"Adventures in Odyssey: Escape from the Forbidden Matrix","release_year":2001,"director":null,"overview":"Dylan can't get enough of the new action-packed video game \"Insectoids\". So when he and his friend Sal are invited to play Insectoids \"for real\" in the virtual-reality Room of Consequence at Whit's En","needs_creation":true,"suggested_slug":"adventures-in-odyssey-escape-from-the-forbidden-matrix-2001"},{"source":"external","title":"Buhera m\u00e1trix","release_year":2007,"director":"Istv\u00e1n M\u00e1rton","overview":"Geri\u2019s life flashes before him in the \u201cBuhera Matrix,\u201d a cringe-worthy slideshow of childhood photos from Pioneer camp to prom night. He relives the absurd clash between Mickey Mouse and sociali","needs_creation":true,"suggested_slug":"buhera-matrix-2007"},{"source":"external","title":"The Matrix Revisited","release_year":2001,"director":"Josh Oreck","overview":"The film goes behind the scenes of the 1999 sci-fi movie The Matrix.","needs_creation":true,"suggested_slug":"the-matrix-revisited-2001"},{"source":"external","title":"Dinosaur Matrix","release_year":1985,"director":"Al Jarnow","overview":"A method by which drawings can be enlarged on a grid.","needs_creation":true,"suggested_slug":"dinosaur-matrix-1985"},{"source":"external","title":"The Matrix Revolutions Revisited","release_year":2004,"director":"Josh Oreck","overview":"The making of The Matrix Revolutions:  The cataclysmic final confrontation chronicled through six documentary pods revealing 28 featurettes","needs_creation":true,"suggested_slug":"the-matrix-revolutions-revisited-2004"}],"total":21,"local_count":1,"external_count":20,"match_type":"ambiguous","confidence":0.5}
```

### Scenario 2: Search by title and year — PASS
- **Request:** `GET http://localhost:8000/api/v1/movies/search?q=matrix&year=1999`
- **Response status:** 200
- **Response body:**
```json
{"results":[{"source":"local","slug":"the-matrix-reloaded-pre-load-2003","title":"the matrix reloaded pre load","release_year":2003,"director":"Mock AI Director","has_description":true},{"source":"external","title":"The Matrix","release_year":1999,"director":"Lana Wachowski","overview":"Set in the 22nd century, The Matrix tells the story of a computer hacker who joins a group of underground insurgents fighting the vast and powerful computers who now rule the earth.","needs_creation":true,"suggested_slug":"the-matrix-1999"},{"source":"external","title":"Making 'The Matrix'","release_year":1999,"director":"Josh Oreck","overview":"A promotional making-of documentary for the film Matrix, The (1999) that devotes its time to explaining the digital and practical effects contained in the film. This is very interesting, seeing as how","needs_creation":true,"suggested_slug":"making-the-matrix-1999"},{"source":"external","title":"The Matrix: What Is Bullet-Time?","release_year":1999,"director":"Josh Oreck","overview":"Special Effects wizard John Gaeta demonstrates how the \"Bullet-Time\" effects were created for the film Matrix, The (1999).","needs_creation":true,"suggested_slug":"the-matrix-what-is-bullet-time-1999"},{"source":"external","title":"V-World Matrix","release_year":1999,"director":"Ron Ford","overview":"Two friends take a cyber vacation to experience a world where they can act out their virtual fantasies! They soon realize they've entered a virtual free-for-all. Forbidden fantasies and desires sudden","needs_creation":true,"suggested_slug":"v-world-matrix-1999"}],"total":5,"local_count":1,"external_count":4,"match_type":"ambiguous","confidence":0.6}
```

### Scenario 2: Search by actor only — FAIL
- **Request:** `GET http://localhost:8000/api/v1/movies/search?actor=Keanu%20Reeves`
- **Response status:** 404 (expected 200)
- **Response body:**
```json
{"error":"No movies found","message":"No movies match your search criteria.","match_type":"none","total":0,"results":[]}
```

### Scenario 2: Search with pagination — PASS
- **Request:** `GET http://localhost:8000/api/v1/movies/search?q=matrix&page=1&per_page=2`
- **Response status:** 200
- **Response body:**
```json
{"results":[{"source":"local","slug":"the-matrix-reloaded-pre-load-2003","title":"the matrix reloaded pre load","release_year":2003,"director":"Mock AI Director","has_description":true},{"source":"external","title":"Matrix","release_year":1973,"director":"Malcolm Le Grice","overview":"Abstract art film made for gallery exhibition.","needs_creation":true,"suggested_slug":"matrix-1973-malcolm-le-grice"}],"total":3,"local_count":1,"external_count":2,"match_type":"ambiguous","confidence":0.8,"pagination":{"current_page":1,"per_page":2,"total_pages":2,"total":3,"has_next_page":true,"has_previous_page":false}}
```

### Scenario 2: Search no results (404) — PASS
- **Request:** `GET http://localhost:8000/api/v1/movies/search?q=NonexistentMovieXYZ123`
- **Response status:** 404
- **Response body:**
```json
{"error":"No movies found","message":"No movies match your search criteria.","match_type":"none","total":0,"results":[]}
```

### Scenario 3: Get Movie Details (the-matrix-1999) — FAIL
- **Request:** `GET http://localhost:8000/api/v1/movies/the-matrix-1999`
- **Response status:** 404 (expected 200)
- **Response body:**
```json
{"error":"Movie not found"}
```

### Scenario 4: Bulk Retrieve (GET slugs) — PASS
- **Request:** `GET http://localhost:8000/api/v1/movies?slugs=the-matrix-1999,inception-2010`
- **Response status:** 200
- **Response body:**
```json
{"data":[],"not_found":["the-matrix-1999","inception-2010"]}
```

### Scenario 4: Bulk with include — PASS
- **Request:** `GET http://localhost:8000/api/v1/movies?slugs=the-matrix-1999&include=descriptions,people,genres`
- **Response status:** 200
- **Response body:**
```json
{"data":[],"not_found":["the-matrix-1999"]}
```

### Scenario 5: Search disambiguation (bad boys) — PASS
- **Request:** `GET http://localhost:8000/api/v1/movies/search?q=bad+boys`
- **Response status:** 200
- **Response body:**
```json
{"results":[{"source":"external","title":"Bad Boys","release_year":1995,"director":"Michael Bay","overview":"Marcus Burnett is a henpecked family man. Mike Lowrey is a footloose and fancy free ladies' man. Both Miami policemen, they have 72 hours to reclaim a consignment of drugs stolen from under their stat","needs_creation":true,"suggested_slug":"bad-boys-1995"},{"source":"external","title":"Bad Boys","release_year":1983,"director":"Rick Rosenthal","overview":"Mick O'Brien is a young Chicago street thug torn between a life of petty crime and the love of his girlfriend. But when the heist of a local drug dealer goes tragically wrong Mick is sentenced to a br","needs_creation":true,"suggested_slug":"bad-boys-1983"},{"source":"external","title":"Bad Boys: Legacy","release_year":2024,"director":null,"overview":"Take an epic lookback at 30 years of making the Bad Boys franchise with a 22-minute retrospective. Go behind the scenes of the all-new movie with an exclusive conversation featuring Will Smith and Mar","needs_creation":true,"suggested_slug":"bad-boys-legacy-2024"},{"source":"external","title":"Bad Boys: A True Story","release_year":2003,"director":"Aleksi M\u00e4kel\u00e4","overview":"The story bases on four Finnish brothers, nicknamed 'the Eura Daltons' who received nation-wide notoriety for tearing gas pumps apart when they needed cash.","needs_creation":true,"suggested_slug":"bad-boys-a-true-story-2003"},{"source":"external","title":"Bad Boys: Ride or Die","release_year":2024,"director":"Adil El Arbi","overview":"After their late former Captain is framed, Lowrey and Burnett try to clear his name, only to end up on the run themselves.","needs_creation":true,"suggested_slug":"bad-boys-ride-or-die-2024"},{"source":"external","title":"Bad Boys for Life","release_year":2020,"director":"Bilall Fallah","overview":"Marcus and Mike are forced to confront new threats, career changes, and midlife crises as they join the newly created elite team AMMO of the Miami police department to take down the ruthless Armando A","needs_creation":true,"suggested_slug":"bad-boys-for-life-2020"},{"source":"external","title":"Bad Boys","release_year":2014,"director":"Zak Levitt","overview":"The Detroit Pistons of the late 1980s and early '90s seemed willing to do anything to win. That characteristic made them loved \u2014 and hated. It earned them the title: Bad Boys.","needs_creation":true,"suggested_slug":"bad-boys-2014"},{"source":"external","title":"For Bad Boys Only","release_year":2000,"director":"Raymond Yip Wai-Man","overview":"The Bad Boy Squad, a variety of private detective agency, is composed of King (Ekin Cheng Yi-kin), Queen (Kristy Yeung), and Jack (Louis Koo Tin-lok), whose primary source of business is the reuniting","needs_creation":true,"suggested_slug":"for-bad-boys-only-2000"},{"source":"external","title":"Bad Boys II","release_year":2003,"director":"Michael Bay","overview":"Detectives Marcus Burnett and Mike Lowrey of the Miami Narcotics Task Force are tasked with stopping the flow of the drug Ecstasy into Miami. They track the drugs to the whacked-out Cuban drug lord Jo","needs_creation":true,"suggested_slug":"bad-boys-ii-2003"},{"source":"external","title":"BAD BOYS J The Movie -The Last Thing to Protect-","release_year":2013,"director":"Takashi Kubota","overview":"A powerful new enemy appears in front of 3 gangs: Gokuraku Cho, Biisuto and Hiroshima Naitsu.\r Based on the manga \"BADBOYS\" by Hiroshi Tanaka (published from 1988 to 1996 by Shonen Gahosha through biw","needs_creation":true,"suggested_slug":"bad-boys-j-the-movie-the-last-thing-to-protect-2013"},{"source":"external","title":"Bad Boys","release_year":null,"director":null,"overview":"","needs_creation":true,"suggested_slug":"bad-boys"},{"source":"external","title":"Bad Boys: The Movie","release_year":2025,"director":"Tatsuro Nishikawa","overview":"Tsukasa Kiriki, the only son of a wealthy family, runs away from home because he admires the legendary delinquent Murakoshi, who has helped him since he was a child. He runs away from home and volunte","needs_creation":true,"suggested_slug":"bad-boys-the-movie-2025"},{"source":"external","title":"Bad Boys","release_year":1961,"director":"Susumu Hani","overview":"A young delinquent takes part in a robbery and is sentenced to a juvenile detention center, where he clashes with other youths and reflects on his life experiences.","needs_creation":true,"suggested_slug":"bad-boys-1961"},{"source":"external","title":"We Love Bad Boys","release_year":2024,"director":"Raju Rajendra Prasad","overview":"Three friends embark on a quest to find girlfriends but planning the perfect date takes an unexpected turn, revealing that love's path is filled with surprises.","needs_creation":true,"suggested_slug":"we-love-bad-boys-2024"},{"source":"external","title":"Bad Blue Boys","release_year":2007,"director":"Branko Schmidt","overview":"A war veteran with post-traumatic disorders talks about his practical inability to start a normal life after his fighting and killing experience. This 35-year-old married man with three children, who ","needs_creation":true,"suggested_slug":"bad-blue-boys-2007"},{"source":"external","title":"Getting Even With Bad Boys","release_year":1998,"director":"Takashi Komatsu","overview":"A gang of tough youths, who have little interest in finding work, while away their days with quarrels and girls in their Kishiwada neighborhood. Based on the novel by Toshikazu Nakaba, who also wrote ","needs_creation":true,"suggested_slug":"getting-even-with-bad-boys-1998"},{"source":"external","title":"Bad Boys of Saturday Night Live","release_year":1998,"director":"Dave Wilson","overview":"Adam Sandler, David Spade, Chris Rock, Rob Schneider and Chris Farley put together this hilarious Saturday Night Live sketch celebration! With each one of their memorable characters: Sandler's Opera M","needs_creation":true,"suggested_slug":"bad-boys-of-saturday-night-live-1998"},{"source":"external","title":"Bad Boys - Bad Toys","release_year":2007,"director":"Jochen Taubert","overview":"Ferdinand doesn't have it easy: first he falls in love with his boss's daughter of all people among 100 hot girls, then he finds out that the company is dealing weapons. When the police suspect him an","needs_creation":true,"suggested_slug":"bad-boys-bad-toys-2007"},{"source":"external","title":"The Bad Boy's Girl","release_year":null,"director":"Mar\u00e7al For\u00e9s","overview":"Tessa O\u2019Connell has only two goals for senior year: keep her head down, and get over the heartbreak of seeing her longtime crush date her ex-best friend. She fears the worst when her childhood bully","needs_creation":true,"suggested_slug":"the-bad-boys-girl"},{"source":"external","title":"The Bad Boy","release_year":1951,"director":"Andrzej Wajda","overview":"First short film by Wajda, based on the story \"A Naughty Boy\" by A. Chekhov.","needs_creation":true,"suggested_slug":"the-bad-boy-1951"}],"total":20,"local_count":0,"external_count":20,"match_type":"ambiguous","confidence":0.5}
```

### Scenario 5: Get movie by slug (bad-boys-ii-2003) — FAIL
- **Request:** `GET http://localhost:8000/api/v1/movies/bad-boys-ii-2003`
- **Response status:** 404 (expected 200)
- **Response body:**
```json
{"error":"Movie not found"}
```

### Scenario 6: Refresh Movie (POST) — FAIL
- **Request:** `POST http://localhost:8000/api/v1/movies/the-matrix-1999/refresh`
- **Response status:** 404 (expected 200)
- **Response body:**
```json
{"error":"Movie not found"}
```

### Scenario 7: Movie Collection — FAIL
- **Request:** `GET http://localhost:8000/api/v1/movies/the-matrix-1999/collection`
- **Response status:** 404 (expected 200)
- **Response body:**
```json
{"error":"Movie not found"}
```

### Scenario 8: Related movies — FAIL
- **Request:** `GET http://localhost:8000/api/v1/movies/the-matrix-1999/related`
- **Response status:** 404 (expected 200)
- **Response body:**
```json
{"error":"Movie not found"}
```

### TC-MOVIE-001: List Movies — PASS
- **Request:** `GET http://localhost:8000/api/v1/movies`
- **Response status:** 200
- **Response body:**
```json
{"data":[{"id":"019c80dc-eefe-7399-b6c0-7728b3dbc1e4","title":"the matrix reloaded pre load","slug":"the-matrix-reloaded-pre-load-2003","release_year":2003,"director":"Mock AI Director","genres":["Sci-Fi","Action"],"default_description_id":"019c80dc-ef04-7015-94e6-bca073d00f4f","created_at":"2026-02-21T15:41:28.000000Z","updated_at":"2026-02-21T15:41:28.000000Z","descriptions_count":2,"default_description":{"id":"019c80dc-ef04-7015-94e6-bca073d00f4f","locale":"en-US","text":"Generated description for the matrix reloaded pre load (en-US locale). This text was produced by MockGenerateMovieJob (AI_SERVICE=mock).","context_tag":"DEFAULT","origin":"GENERATED","ai_model":"mock-ai-1"},"people":[],"_links":{"self":{"href":"http:\/\/localhost:8000\/api\/v1\/movies\/the-matrix-reloaded-pre-load-2003"},"generate":{"href":"http:\/\/localhost:8000\/api\/v1\/generate","method":"POST","body":{"entity_type":"MOVIE","entity_id":"019c80dc-eefe-7399-b6c0-7728b3dbc1e4"}},"people":[]}}]}
```

### TC-MOVIE-002: Get Movie by Slug — FAIL
- **Request:** `GET http://localhost:8000/api/v1/movies/the-matrix-1999`
- **Response status:** 404 (expected 200)
- **Response body:**
```json
{"error":"Movie not found"}
```

### TC-MOVIE-003: Search Movies — PASS
- **Request:** `GET http://localhost:8000/api/v1/movies/search?q=matrix`
- **Response status:** 200
- **Response body:**
```json
{"results":[{"source":"local","slug":"the-matrix-reloaded-pre-load-2003","title":"the matrix reloaded pre load","release_year":2003,"director":"Mock AI Director","has_description":true},{"source":"external","title":"Matrix","release_year":1973,"director":"Malcolm Le Grice","overview":"Abstract art film made for gallery exhibition.","needs_creation":true,"suggested_slug":"matrix-1973-malcolm-le-grice"},{"source":"external","title":"Matrix","release_year":1973,"director":"James Cagle","overview":"\"MATRIX is a flicker film which utilizes 81 still photographs of my wife's head. It is a film dependent upon variation of intense light changes by calculated combinations of black and white frame alte","needs_creation":true,"suggested_slug":"matrix-1973-james-cagle"},{"source":"external","title":"Matrix","release_year":1998,"director":"Nicky Hamlyn","overview":"The film is composed of receding planes in a landscape: a back garden and the houses beyond. The wooden lattice fence, visible in the image, marks the border between enclosed and open, private and pub","needs_creation":true,"suggested_slug":"matrix-1998"},{"source":"external","title":"Armitage: Dual Matrix","release_year":2002,"director":"Katsuhito Akiyama","overview":"Naomi Armitage and Ross Sylibus have changed their names and live with their daughter Yoko as a happy and normal family on Mars \u2014 until an android riot breaks out at an anti-matter plant on Earth.","needs_creation":true,"suggested_slug":"armitage-dual-matrix-2002"},{"source":"external","title":"Armitage III: Poly Matrix","release_year":1996,"director":"Takuya Sato","overview":"Ross Sylibus is assigned to a police unit on a Martian colony, to find that women are being murdered by a psychotic named D'anclaude. He is assigned a very unorthodox partner named Naomi Armitage, who","needs_creation":true,"suggested_slug":"armitage-iii-poly-matrix-1996"},{"source":"external","title":"The Matrix","release_year":1999,"director":"Lana Wachowski","overview":"Set in the 22nd century, The Matrix tells the story of a computer hacker who joins a group of underground insurgents fighting the vast and powerful computers who now rule the earth.","needs_creation":true,"suggested_slug":"the-matrix-1999"},{"source":"external","title":"The Matrix: Generation","release_year":2023,"director":"Benjamin Clavel","overview":"After the 1999 premiere of the first Matrix movie, it became a pop culture phenomenon. A special documentary about the Matrix saga and its prophetic aspects.","needs_creation":true,"suggested_slug":"the-matrix-generation-2023"},{"source":"external","title":"The Matrix Reloaded","release_year":2003,"director":"Lilly Wachowski","overview":"The Resistance builds in numbers as humans are freed from the Matrix and brought to the city of Zion. Neo discovers his superpowers, including the ability to see the code inside the Matrix. With machi","needs_creation":true,"suggested_slug":"the-matrix-reloaded-2003"},{"source":"external","title":"The Matrix Resurrections","release_year":2021,"director":"Lana Wachowski","overview":"Plagued by strange memories, Neo's life takes an unexpected turn when he finds himself back inside the Matrix.","needs_creation":true,"suggested_slug":"the-matrix-resurrections-2021"},{"source":"external","title":"The Matrix Revolutions","release_year":2003,"director":"Lilly Wachowski","overview":"The human city of Zion defends itself against the massive invasion of the machines as Neo fights to end the war at another front while also opposing the rogue Agent Smith.","needs_creation":true,"suggested_slug":"the-matrix-revolutions-2003"},{"source":"external","title":"Matrix Dream Maze","release_year":2023,"director":"Guo Wenji","overview":"Legend has it that people who meet the dream demon are trapped there forever.","needs_creation":true,"suggested_slug":"matrix-dream-maze-2023"},{"source":"external","title":"Chess Boxing Matrix","release_year":1988,"director":"Chih-Cheng Wang","overview":"Action packed vampire kung fu movie produced by Joseph Kuo. Jack Long portrays a fighting Taoist priest, who helps with the aid of his disciples to reunite a baby vampire with his parents from the \"Ki","needs_creation":true,"suggested_slug":"chess-boxing-matrix-1988"},{"source":"external","title":"The Matrix","release_year":null,"director":"Pedro Guimar\u00e3es","overview":"\"A Matriz\" is a short documentary about Pra\u00e7a da Matriz, an important square in Porto Alegre. Through images, stories, and reflections, it explores the place\u2019s history, architecture, and role in ev","needs_creation":true,"suggested_slug":"the-matrix"},{"source":"external","title":"180 Years of Matrix Croatica","release_year":2022,"director":"Leon Rizmaul","overview":"A documentary on the first 180 years of Matrix Croatica, the leading organization for promoting, studying, recording and funding all aspects of Croatia's cultural heritage in the Balkans and beyond.","needs_creation":true,"suggested_slug":"180-years-of-matrix-croatica-2022"},{"source":"external","title":"Exit The Matrix","release_year":2019,"director":"Alexandra Prikhodko","overview":"In the Absheron region of the Krasnodar Territory, among the majestic Caucasus Mountains and impenetrable forests, small villages were lost. They are connected to the rest of the world only by a thin ","needs_creation":true,"suggested_slug":"exit-the-matrix-2019"},{"source":"external","title":"Adventures in Odyssey: Escape from the Forbidden Matrix","release_year":2001,"director":null,"overview":"Dylan can't get enough of the new action-packed video game \"Insectoids\". So when he and his friend Sal are invited to play Insectoids \"for real\" in the virtual-reality Room of Consequence at Whit's En","needs_creation":true,"suggested_slug":"adventures-in-odyssey-escape-from-the-forbidden-matrix-2001"},{"source":"external","title":"Buhera m\u00e1trix","release_year":2007,"director":"Istv\u00e1n M\u00e1rton","overview":"Geri\u2019s life flashes before him in the \u201cBuhera Matrix,\u201d a cringe-worthy slideshow of childhood photos from Pioneer camp to prom night. He relives the absurd clash between Mickey Mouse and sociali","needs_creation":true,"suggested_slug":"buhera-matrix-2007"},{"source":"external","title":"The Matrix Revisited","release_year":2001,"director":"Josh Oreck","overview":"The film goes behind the scenes of the 1999 sci-fi movie The Matrix.","needs_creation":true,"suggested_slug":"the-matrix-revisited-2001"},{"source":"external","title":"Dinosaur Matrix","release_year":1985,"director":"Al Jarnow","overview":"A method by which drawings can be enlarged on a grid.","needs_creation":true,"suggested_slug":"dinosaur-matrix-1985"},{"source":"external","title":"The Matrix Revolutions Revisited","release_year":2004,"director":"Josh Oreck","overview":"The making of The Matrix Revolutions:  The cataclysmic final confrontation chronicled through six documentary pods revealing 28 featurettes","needs_creation":true,"suggested_slug":"the-matrix-revolutions-revisited-2004"}],"total":21,"local_count":1,"external_count":20,"match_type":"ambiguous","confidence":0.5}
```

### TC-MOVIE-004: Bulk Retrieve (POST) — PASS
- **Request:** `POST http://localhost:8000/api/v1/movies/bulk | body: {"slugs":["the-matrix-1999","inception-2010"]}`
- **Response status:** 200
- **Response body:**
```json
{"data":[],"not_found":["the-matrix-1999","inception-2010"]}
```

### TC-MOVIE-005: Compare Movies — FAIL
- **Request:** `GET http://localhost:8000/api/v1/movies/compare?slug1=the-matrix-1999&slug2=inception-2010`
- **Response status:** 404 (expected 200)
- **Response body:**
```json
{"error":"Movie not found"}
```

### TC-MOVIE-006: Get Related Movies — FAIL
- **Request:** `GET http://localhost:8000/api/v1/movies/the-matrix-1999/related`
- **Response status:** 404 (expected 200)
- **Response body:**
```json
{"error":"Movie not found"}
```

### TC-MOVIE-007: Get Movie Collection — FAIL
- **Request:** `GET http://localhost:8000/api/v1/movies/the-matrix-1999/collection`
- **Response status:** 404 (expected 200)
- **Response body:**
```json
{"error":"Movie not found"}
```

### TC-MOVIE-008: Refresh Movie Data — FAIL
- **Request:** `POST http://localhost:8000/api/v1/movies/the-matrix-1999/refresh`
- **Response status:** 404 (expected 200)
- **Response body:**
```json
{"error":"Movie not found"}
```

### TC-MOVIE-009: Report Movie Issue — FAIL
- **Request:** `POST http://localhost:8000/api/v1/movies/the-matrix-1999/report | body: {"type":"factual_error","message":"Test report from manual scenarios"}`
- **Response status:** 404 (expected 201)
- **Response body:**
```json
{"error":"Movie not found"}
```

### TC-MOVIE-010: Disambiguation (search bad+boys) — PASS
- **Request:** `GET http://localhost:8000/api/v1/movies/search?q=bad+boys`
- **Response status:** 200
- **Response body:**
```json
{"results":[{"source":"external","title":"Bad Boys","release_year":1995,"director":"Michael Bay","overview":"Marcus Burnett is a henpecked family man. Mike Lowrey is a footloose and fancy free ladies' man. Both Miami policemen, they have 72 hours to reclaim a consignment of drugs stolen from under their stat","needs_creation":true,"suggested_slug":"bad-boys-1995"},{"source":"external","title":"Bad Boys","release_year":1983,"director":"Rick Rosenthal","overview":"Mick O'Brien is a young Chicago street thug torn between a life of petty crime and the love of his girlfriend. But when the heist of a local drug dealer goes tragically wrong Mick is sentenced to a br","needs_creation":true,"suggested_slug":"bad-boys-1983"},{"source":"external","title":"Bad Boys: Legacy","release_year":2024,"director":null,"overview":"Take an epic lookback at 30 years of making the Bad Boys franchise with a 22-minute retrospective. Go behind the scenes of the all-new movie with an exclusive conversation featuring Will Smith and Mar","needs_creation":true,"suggested_slug":"bad-boys-legacy-2024"},{"source":"external","title":"Bad Boys: A True Story","release_year":2003,"director":"Aleksi M\u00e4kel\u00e4","overview":"The story bases on four Finnish brothers, nicknamed 'the Eura Daltons' who received nation-wide notoriety for tearing gas pumps apart when they needed cash.","needs_creation":true,"suggested_slug":"bad-boys-a-true-story-2003"},{"source":"external","title":"Bad Boys: Ride or Die","release_year":2024,"director":"Adil El Arbi","overview":"After their late former Captain is framed, Lowrey and Burnett try to clear his name, only to end up on the run themselves.","needs_creation":true,"suggested_slug":"bad-boys-ride-or-die-2024"},{"source":"external","title":"Bad Boys for Life","release_year":2020,"director":"Bilall Fallah","overview":"Marcus and Mike are forced to confront new threats, career changes, and midlife crises as they join the newly created elite team AMMO of the Miami police department to take down the ruthless Armando A","needs_creation":true,"suggested_slug":"bad-boys-for-life-2020"},{"source":"external","title":"Bad Boys","release_year":2014,"director":"Zak Levitt","overview":"The Detroit Pistons of the late 1980s and early '90s seemed willing to do anything to win. That characteristic made them loved \u2014 and hated. It earned them the title: Bad Boys.","needs_creation":true,"suggested_slug":"bad-boys-2014"},{"source":"external","title":"For Bad Boys Only","release_year":2000,"director":"Raymond Yip Wai-Man","overview":"The Bad Boy Squad, a variety of private detective agency, is composed of King (Ekin Cheng Yi-kin), Queen (Kristy Yeung), and Jack (Louis Koo Tin-lok), whose primary source of business is the reuniting","needs_creation":true,"suggested_slug":"for-bad-boys-only-2000"},{"source":"external","title":"Bad Boys II","release_year":2003,"director":"Michael Bay","overview":"Detectives Marcus Burnett and Mike Lowrey of the Miami Narcotics Task Force are tasked with stopping the flow of the drug Ecstasy into Miami. They track the drugs to the whacked-out Cuban drug lord Jo","needs_creation":true,"suggested_slug":"bad-boys-ii-2003"},{"source":"external","title":"BAD BOYS J The Movie -The Last Thing to Protect-","release_year":2013,"director":"Takashi Kubota","overview":"A powerful new enemy appears in front of 3 gangs: Gokuraku Cho, Biisuto and Hiroshima Naitsu.\r Based on the manga \"BADBOYS\" by Hiroshi Tanaka (published from 1988 to 1996 by Shonen Gahosha through biw","needs_creation":true,"suggested_slug":"bad-boys-j-the-movie-the-last-thing-to-protect-2013"},{"source":"external","title":"Bad Boys","release_year":null,"director":null,"overview":"","needs_creation":true,"suggested_slug":"bad-boys"},{"source":"external","title":"Bad Boys: The Movie","release_year":2025,"director":"Tatsuro Nishikawa","overview":"Tsukasa Kiriki, the only son of a wealthy family, runs away from home because he admires the legendary delinquent Murakoshi, who has helped him since he was a child. He runs away from home and volunte","needs_creation":true,"suggested_slug":"bad-boys-the-movie-2025"},{"source":"external","title":"Bad Boys","release_year":1961,"director":"Susumu Hani","overview":"A young delinquent takes part in a robbery and is sentenced to a juvenile detention center, where he clashes with other youths and reflects on his life experiences.","needs_creation":true,"suggested_slug":"bad-boys-1961"},{"source":"external","title":"We Love Bad Boys","release_year":2024,"director":"Raju Rajendra Prasad","overview":"Three friends embark on a quest to find girlfriends but planning the perfect date takes an unexpected turn, revealing that love's path is filled with surprises.","needs_creation":true,"suggested_slug":"we-love-bad-boys-2024"},{"source":"external","title":"Bad Blue Boys","release_year":2007,"director":"Branko Schmidt","overview":"A war veteran with post-traumatic disorders talks about his practical inability to start a normal life after his fighting and killing experience. This 35-year-old married man with three children, who ","needs_creation":true,"suggested_slug":"bad-blue-boys-2007"},{"source":"external","title":"Getting Even With Bad Boys","release_year":1998,"director":"Takashi Komatsu","overview":"A gang of tough youths, who have little interest in finding work, while away their days with quarrels and girls in their Kishiwada neighborhood. Based on the novel by Toshikazu Nakaba, who also wrote ","needs_creation":true,"suggested_slug":"getting-even-with-bad-boys-1998"},{"source":"external","title":"Bad Boys of Saturday Night Live","release_year":1998,"director":"Dave Wilson","overview":"Adam Sandler, David Spade, Chris Rock, Rob Schneider and Chris Farley put together this hilarious Saturday Night Live sketch celebration! With each one of their memorable characters: Sandler's Opera M","needs_creation":true,"suggested_slug":"bad-boys-of-saturday-night-live-1998"},{"source":"external","title":"Bad Boys - Bad Toys","release_year":2007,"director":"Jochen Taubert","overview":"Ferdinand doesn't have it easy: first he falls in love with his boss's daughter of all people among 100 hot girls, then he finds out that the company is dealing weapons. When the police suspect him an","needs_creation":true,"suggested_slug":"bad-boys-bad-toys-2007"},{"source":"external","title":"The Bad Boy's Girl","release_year":null,"director":"Mar\u00e7al For\u00e9s","overview":"Tessa O\u2019Connell has only two goals for senior year: keep her head down, and get over the heartbreak of seeing her longtime crush date her ex-best friend. She fears the worst when her childhood bully","needs_creation":true,"suggested_slug":"the-bad-boys-girl"},{"source":"external","title":"The Bad Boy","release_year":1951,"director":"Andrzej Wajda","overview":"First short film by Wajda, based on the story \"A Naughty Boy\" by A. Chekhov.","needs_creation":true,"suggested_slug":"the-bad-boy-1951"}],"total":20,"local_count":0,"external_count":20,"match_type":"ambiguous","confidence":0.5}
```

### Scenario Health: Health Check — PASS
- **Request:** `GET http://localhost:8000/api/v1/health`
- **Response status:** 200
- **Response body:**
```json
{"status":"healthy","timestamp":"2026-02-21T16:03:18+00:00","checks":{"database":{"status":"ok","message":"Database connection established"},"openai":{"success":true,"message":"OpenAI API reachable","status":200,"model":"gpt-4o-mini","rate_limit":[]},"tmdb":{"success":true,"service":"tmdb","message":"TMDb API is accessible","status":200},"tvmaze":{"success":true,"service":"tvmaze","message":"TVmaze API is accessible","status":200},"instance":{"instance_id":"api-1-local","status":"healthy","features":[]}}}
```
