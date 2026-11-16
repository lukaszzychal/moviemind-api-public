import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import { createRouter, createMemoryHistory } from 'vue-router'
import router, { routes } from '@/router'
import NotFound from '@/views/NotFound.vue'
import en from '@/locales/en.json'
import pl from '@/locales/pl.json'
import de from '@/locales/de.json'

describe('unknown URLs', () => {
  it.each(['/no-such-page', '/movies', '/movies/shrek-2001/extra', '/admin'])(
    'resolves %s to the NotFound route',
    (path) => {
      expect(router.resolve(path).name).toBe('NotFound')
    }
  )

  it('keeps known routes working', () => {
    expect(router.resolve('/').name).toBe('Home')
    expect(router.resolve('/search').name).toBe('Search')
    expect(router.resolve('/movies/shrek-2001').name).toBe('MovieDetail')
  })
})

describe('NotFound view', () => {
  it.each([['en', en], ['pl', pl], ['de', de]])('has translations in %s', (_locale, messages) => {
    expect(messages.not_found?.title).toBeTruthy()
    expect(messages.not_found?.message).toBeTruthy()
    expect(messages.not_found?.back_home).toBeTruthy()
  })

  it('renders a message and a link back to the home page', async () => {
    const i18n = createI18n({ legacy: false, locale: 'en', messages: { en } })
    const testRouter = createRouter({ history: createMemoryHistory(), routes })
    testRouter.push('/no-such-page')
    await testRouter.isReady()

    const wrapper = mount(NotFound, { global: { plugins: [i18n, testRouter] } })

    expect(wrapper.text()).toContain(en.not_found.title)
    expect(wrapper.text()).toContain(en.not_found.message)
    const link = wrapper.find('a[href="/"]')
    expect(link.exists()).toBe(true)
    expect(link.text()).toBe(en.not_found.back_home)
  })
})
