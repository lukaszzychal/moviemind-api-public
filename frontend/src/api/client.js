/**
 * API client for MovieMind public v1. Base URL from config (proxy in dev).
 * In dev we use relative /api/v1 so Vite proxy forwards to the API (no CORS).
 * In production, use VITE_API_BASE_URL if set (e.g. different domain).
 * POST /generate requires X-API-Key; other endpoints do not.
 */
const base =
  import.meta.env.DEV
    ? '/api/v1'
    : (import.meta.env.VITE_API_BASE_URL ?? '') + '/api/v1'

function get (path, options = {}) {
  return fetch(`${base}${path}`, {
    method: 'GET',
    headers: { Accept: 'application/json', ...options.headers },
    ...options,
  })
}

function post (path, body, options = {}) {
  return fetch(`${base}${path}`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...options.headers,
    },
    body: body ? JSON.stringify(body) : undefined,
    ...options,
  })
}

async function parseResponse (response) {
  const text = await response.text()
  const data = text ? JSON.parse(text) : null
  if (!response.ok) {
    const err = new Error(data?.message || response.statusText || `HTTP ${response.status}`)
    err.status = response.status
    err.data = data
    throw err
  }
  return data
}

// Movies
export async function getMovies (params = {}) {
  const q = new URLSearchParams(params).toString()
  const res = await get(`/movies${q ? `?${q}` : ''}`)
  return parseResponse(res)
}

export async function searchMovies (params = {}) {
  const q = new URLSearchParams(params).toString()
  const res = await get(`/movies/search?${q}`)
  return parseResponse(res)
}

export async function getMovie (slug, query = {}) {
  const q = new URLSearchParams(query).toString()
  const res = await get(`/movies/${encodeURIComponent(slug)}${q ? `?${q}` : ''}`)
  return parseResponse(res)
}

export async function getMovieRelated (slug) {
  const res = await get(`/movies/${encodeURIComponent(slug)}/related`)
  return parseResponse(res)
}

export async function getMovieCollection (slug) {
  const res = await get(`/movies/${encodeURIComponent(slug)}/collection`)
  return parseResponse(res)
}

export async function compareMovies (slug1, slug2) {
  const q = new URLSearchParams({ slug1, slug2 })
  const res = await get(`/movies/compare?${q.toString()}`)
  return parseResponse(res)
}

export async function reportMovie (slug, body = {}) {
  const res = await post(`/movies/${encodeURIComponent(slug)}/report`, body)
  return parseResponse(res)
}

// People
export async function getPeople (params = {}) {
  const q = new URLSearchParams(params).toString()
  const res = await get(`/people${q ? `?${q}` : ''}`)
  return parseResponse(res)
}

export async function searchPeople (params = {}) {
  const q = new URLSearchParams(params).toString()
  const res = await get(`/people/search?${q}`)
  return parseResponse(res)
}

export async function getPerson (slug, query = {}) {
  const q = new URLSearchParams(query).toString()
  const res = await get(`/people/${encodeURIComponent(slug)}${q ? `?${q}` : ''}`)
  return parseResponse(res)
}

export async function getPersonRelated (slug) {
  const res = await get(`/people/${encodeURIComponent(slug)}/related`)
  return parseResponse(res)
}

export async function comparePeople (slug1, slug2) {
  const q = new URLSearchParams({ slug1, slug2 })
  const res = await get(`/people/compare?${q.toString()}`)
  return parseResponse(res)
}

export async function reportPerson (slug, body = {}) {
  const res = await post(`/people/${encodeURIComponent(slug)}/report`, body)
  return parseResponse(res)
}

// TV Series
export async function getTvSeries (params = {}) {
  const q = new URLSearchParams(params).toString()
  const res = await get(`/tv-series${q ? `?${q}` : ''}`)
  return parseResponse(res)
}

export async function searchTvSeries (params = {}) {
  const q = new URLSearchParams(params).toString()
  const res = await get(`/tv-series/search?${q}`)
  return parseResponse(res)
}

export async function getTvSeriesBySlug (slug, query = {}) {
  const q = new URLSearchParams(query).toString()
  const res = await get(`/tv-series/${encodeURIComponent(slug)}${q ? `?${q}` : ''}`)
  return parseResponse(res)
}

export async function getTvSeriesRelated (slug) {
  const res = await get(`/tv-series/${encodeURIComponent(slug)}/related`)
  return parseResponse(res)
}

export async function compareTvSeries (slug1, slug2) {
  const q = new URLSearchParams({ slug1, slug2 })
  const res = await get(`/tv-series/compare?${q.toString()}`)
  return parseResponse(res)
}

export async function reportTvSeries (slug, body = {}) {
  const res = await post(`/tv-series/${encodeURIComponent(slug)}/report`, body)
  return parseResponse(res)
}

// TV Shows
export async function getTvShows (params = {}) {
  const q = new URLSearchParams(params).toString()
  const res = await get(`/tv-shows${q ? `?${q}` : ''}`)
  return parseResponse(res)
}

export async function searchTvShows (params = {}) {
  const q = new URLSearchParams(params).toString()
  const res = await get(`/tv-shows/search?${q}`)
  return parseResponse(res)
}

export async function getTvShow (slug, query = {}) {
  const q = new URLSearchParams(query).toString()
  const res = await get(`/tv-shows/${encodeURIComponent(slug)}${q ? `?${q}` : ''}`)
  return parseResponse(res)
}

export async function getTvShowRelated (slug) {
  const res = await get(`/tv-shows/${encodeURIComponent(slug)}/related`)
  return parseResponse(res)
}

export async function compareTvShows (slug1, slug2) {
  const q = new URLSearchParams({ slug1, slug2 })
  const res = await get(`/tv-shows/compare?${q.toString()}`)
  return parseResponse(res)
}

export async function reportTvShow (slug, body = {}) {
  const res = await post(`/tv-shows/${encodeURIComponent(slug)}/report`, body)
  return parseResponse(res)
}

// Generation (requires X-API-Key)
export async function postGenerate (body, apiKey) {
  const headers = {}
  if (apiKey) headers['X-API-Key'] = apiKey
  const res = await post('/generate', body, { headers })
  return parseResponse(res)
}

// Jobs
export async function getJob (id) {
  const res = await get(`/jobs/${encodeURIComponent(id)}`)
  return parseResponse(res)
}

// Feedback
export async function postFeedback (body) {
  const res = await post('/feedback', body)
  return parseResponse(res)
}
