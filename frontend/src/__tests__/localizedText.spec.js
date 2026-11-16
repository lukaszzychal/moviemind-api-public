import { describe, it, expect } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import { contentLocaleFor, pickLocalized, languageName } from '@/utils/localizedText'
import LocaleNotice from '@/components/LocaleNotice.vue'
import en from '@/locales/en.json'
import pl from '@/locales/pl.json'
import de from '@/locales/de.json'

const plBio = { id: 'pl1', locale: 'pl-PL', text: 'Po polsku' }
const enBio = { id: 'en1', locale: 'en-US', text: 'In English' }
const deBio = { id: 'de1', locale: 'de-DE', text: 'Auf Deutsch' }

describe('contentLocaleFor', () => {
  it.each([['en', 'en-US'], ['pl', 'pl-PL'], ['de', 'de-DE']])('maps %s to %s', (ui, content) => {
    expect(contentLocaleFor(ui)).toBe(content)
  })
})

describe('pickLocalized', () => {
  it('prefers the version in the interface language over the default', () => {
    const result = pickLocalized({ items: [plBio, enBio], defaultItem: plBio, uiLocale: 'en' })
    expect(result.item).toBe(enBio)
    expect(result.isOtherLanguage).toBe(false)
  })

  it('keeps the default when it is already in the interface language', () => {
    const otherEn = { ...enBio, id: 'en2' }
    const result = pickLocalized({ items: [otherEn, enBio], defaultItem: enBio, uiLocale: 'en' })
    expect(result.item).toBe(enBio)
  })

  it('falls back to the default and flags the other language when no match exists', () => {
    const result = pickLocalized({ items: [plBio], defaultItem: plBio, uiLocale: 'en' })
    expect(result.item).toBe(plBio)
    expect(result.isOtherLanguage).toBe(true)
  })

  it('falls back to the first item when there is no default', () => {
    const result = pickLocalized({ items: [deBio, plBio], defaultItem: null, uiLocale: 'en' })
    expect(result.item).toBe(deBio)
    expect(result.isOtherLanguage).toBe(true)
  })

  it('honours an explicitly requested version without the notice when the interface language exists', () => {
    const result = pickLocalized({ items: [plBio, enBio], defaultItem: enBio, uiLocale: 'en', requestedId: 'pl1' })
    expect(result.item).toBe(plBio)
    expect(result.isOtherLanguage).toBe(false)
  })

  it('keeps the notice for a requested version when the interface language is missing', () => {
    const result = pickLocalized({ items: [plBio, deBio], defaultItem: plBio, uiLocale: 'en', requestedId: 'de1' })
    expect(result.item).toBe(deBio)
    expect(result.isOtherLanguage).toBe(true)
  })

  it('returns nothing when there is no text', () => {
    expect(pickLocalized({ items: [], defaultItem: null, uiLocale: 'en' })).toEqual({ item: null, isOtherLanguage: false })
  })
})

describe('languageName', () => {
  it('names the language in the interface language', () => {
    expect(languageName('pl-PL', 'en')).toBe('Polish')
    expect(languageName('en-US', 'pl')).toBe('angielski')
  })
})

describe('LocaleNotice', () => {
  it.each([['en', en], ['pl', pl], ['de', de]])('has translations in %s', (_locale, messages) => {
    expect(messages.detail.other_language).toContain('{language}')
    expect(messages.detail.generate_in_language).toContain('{language}')
  })

  it('says which language the text is in and links to generation in the interface language', () => {
    const i18n = createI18n({ legacy: false, locale: 'en', messages: { en } })
    const wrapper = mount(LocaleNotice, {
      props: { contentLocale: 'pl-PL', entityType: 'PERSON', slug: 'keanu-reeves' },
      global: { plugins: [i18n], stubs: { RouterLink: RouterLinkStub } },
    })

    expect(wrapper.text()).toContain('Polish')
    expect(wrapper.text()).toContain('English')
    const link = wrapper.findComponent(RouterLinkStub)
    expect(link.props('to')).toEqual({
      name: 'Generate',
      query: { entity_type: 'PERSON', slug: 'keanu-reeves', locale: 'en-US' },
    })
  })
})
