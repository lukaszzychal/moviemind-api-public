/**
 * Composable for formatting dates in a user-friendly way.
 */

/**
 * Format a date string to a locale-friendly format.
 * Handles both ISO datetime strings (e.g. 1962-07-03T00:00:00.000000Z)
 * and plain date strings (e.g. 1962-07-03 or 2020-01-01).
 *
 * @param {string|null} dateStr
 * @param {string} locale - BCP 47 locale string (e.g. 'en', 'pl', 'de')
 * @param {'date'|'year'} [format='date']
 * @returns {string|null}
 */
export function formatDate (dateStr, locale = 'en', format = 'date') {
  if (!dateStr) return null

  // Parse safely - treat plain YYYY-MM-DD as UTC to avoid local-offset issues
  let date
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
    // Plain date string: parse as UTC noon to avoid timezone shifts
    const [y, m, d] = dateStr.split('-').map(Number)
    date = new Date(Date.UTC(y, m - 1, d, 12, 0, 0))
  } else {
    date = new Date(dateStr)
  }

  if (isNaN(date.getTime())) return dateStr

  if (format === 'year') {
    return date.getUTCFullYear().toString()
  }

  return new Intl.DateTimeFormat(locale, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    timeZone: 'UTC',
  }).format(date)
}
