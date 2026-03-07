# API access – plans, keys, public token (notes)

Not committed to repo (see .gitignore).

---

## Plan names and API key names (all free, rate-limited)

| Use case | Plan `name` | Plan `display_name` | API key name | Monthly limit | Rate/min | Features |
|----------|-------------|---------------------|--------------|---------------|----------|----------|
| Frontend (own app) | `frontend` | Frontend | MovieMind Frontend | 50 000 | 200 | read, generate, context_tags |
| Public (users / demo) | `public` | Public | Public API Key | 5 000 | 30 | read |
| RapidAPI Free | `rapidapi_free` | RapidAPI Free | RapidAPI Free | 10 000 | 60 | read, generate |

---

## Where to put the public token

- **Welcome page** – e.g. “Try the API” with curl example or code snippet including the key, or “Copy request” button.
- **Frontend** – token in env (e.g. `VITE_PUBLIC_API_KEY`), used for API calls from the browser.

**Bots:** If the token is in HTML/JS, bots can read it. Protection is **rate limit**, not secrecy. Public key keeps a low limit (e.g. 30/min, 5k/month) so abuse is bounded. Use a separate key for frontend with higher limit; rotate/block in admin if abused.


API Key Created Successfully
Your new API key is: **mm_wAC3oeHrGyE3vlcVoJOik3GaiSA3Kf0V3YpQxAr7** Please copy it now. You won't be able to see it again.

API Key Created Successfully
Your new API key is: **mm_Ce9g6ezFmVWa-xKNM3XbbWzKLRIlUQLzGy-Rgaog** Please copy it now. You won't be able to see it again.

API Key Created Successfully
Your new API key is: **mm_-vO0LzhHO5lKWucVguB5Vz_u2ijxhF34Nxj_AvZG** Please copy it now. You won't be able to see it again.