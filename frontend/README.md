# MovieMind Frontend

Vue 3 + Vite + Tailwind SPA for the [MovieMind API](../api/).

## Setup

```bash
cd frontend
cp .env.example .env
npm install
```

## Development

Start the dev server (with proxy to API at `VITE_API_BASE_URL`):

```bash
npm run dev
```

Open http://localhost:5173. API calls to `/api/*` are proxied to the backend (default `http://localhost:8000`). Ensure the API is running (e.g. `docker compose up -d` from repo root).

## Build

```bash
npm run build
```

Output in `dist/`. Deploy to any static host (Vercel, Netlify, S3+CloudFront, or same server as API).

## Environment

| Variable | Description |
|----------|-------------|
| `VITE_API_BASE_URL` | API base URL for dev proxy (default: `http://localhost:8000`) |

In production, configure your API base URL (e.g. via env in your deploy platform); the app can use `import.meta.env.VITE_API_BASE_URL` for direct API calls if needed.
