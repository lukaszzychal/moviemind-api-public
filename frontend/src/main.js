import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import './style.css'
import { createI18n } from 'vue-i18n'

import en from './locales/en.json'
import pl from './locales/pl.json'
import de from './locales/de.json'

const i18n = createI18n({
    legacy: false,
    locale: localStorage.getItem('locale') || 'en',
    fallbackLocale: 'en',
    messages: { en, pl, de }
})

const app = createApp(App)

app.use(router)
app.use(i18n)

router.isReady().then(() => {
    app.mount('#app')
})
