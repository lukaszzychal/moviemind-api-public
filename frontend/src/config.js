/**
 * Runtime config. In dev, Vite proxy forwards /api to VITE_API_BASE_URL.
 * In production, set VITE_API_BASE_URL at build time or ensure the app is
 * served from the same origin as the API (or configure your host to proxy /api).
 */
export const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? ''
