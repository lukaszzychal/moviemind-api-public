const CONTENT_LOCALES = { en: 'en-US', pl: 'pl-PL', de: 'de-DE' }

/** Content locale (e.g. "pl-PL") used for AI texts in the given interface language ("pl"). */
export function contentLocaleFor (uiLocale) {
  return CONTENT_LOCALES[uiLocale] ?? uiLocale
}

/**
 * Choose which description/bio to show. An explicitly requested version wins;
 * otherwise the one in the interface language (the default if it matches), then
 * the default, then the first available. `isOtherLanguage` tells the page to
 * explain that the text is in another language; it stays false when a version
 * in the interface language exists and another one was picked on purpose.
 */
export function pickLocalized ({ items, defaultItem, uiLocale, requestedId = null }) {
  const list = items ?? []
  const wanted = contentLocaleFor(uiLocale)

  const requested = requestedId == null
    ? null
    : list.find(entry => String(entry.id) === String(requestedId))

  const inInterfaceLanguage = defaultItem?.locale === wanted
    ? defaultItem
    : list.find(entry => entry.locale === wanted)

  const item = requested ?? inInterfaceLanguage ?? defaultItem ?? list[0] ?? null

  return {
    item,
    isOtherLanguage: Boolean(item?.locale) && item.locale !== wanted && !inInterfaceLanguage,
  }
}

/** Human-readable name of a content locale's language, in the interface language. */
export function languageName (contentLocale, uiLocale) {
  const language = String(contentLocale).split('-')[0]
  try {
    return new Intl.DisplayNames([uiLocale], { type: 'language' }).of(language) ?? contentLocale
  } catch {
    return contentLocale
  }
}
