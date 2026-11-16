const API_PREFIX = '/api/v1'

/**
 * Base URL for API calls. In development the Vite proxy serves /api/v1.
 * In production VITE_API_BASE_URL may be the API origin, with or without
 * the /api/v1 prefix; the prefix is added exactly once.
 */
export function resolveApiBase (envValue, isDev) {
  if (isDev) return API_PREFIX

  const origin = (envValue ?? '')
    .trim()
    .replace(/\/+$/, '')
    .replace(/\/api\/v1$/, '')

  return `${origin}${API_PREFIX}`
}
