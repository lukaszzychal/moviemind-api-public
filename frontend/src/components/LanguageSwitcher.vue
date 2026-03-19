<script setup>
import { useI18n } from 'vue-i18n'
import { watch } from 'vue'

const { locale } = useI18n()

// Keep localStorage in sync when locale changes
watch(locale, (newLocale) => {
  localStorage.setItem('locale', newLocale)
})

const options = [
  { value: 'en', label: 'EN', title: 'English' },
  { value: 'pl', label: 'PL', title: 'Polski' },
  { value: 'de', label: 'DE', title: 'Deutsch' },
]
</script>

<template>
  <div class="relative inline-block text-left">
    <select
      v-model="locale"
      class="appearance-none bg-transparent hover:bg-gray-100 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 outline-none transition-colors border-none cursor-pointer focus:ring-2 focus:ring-indigo-500/20"
      aria-label="Select language"
    >
      <option
        v-for="opt in options"
        :key="opt.value"
        :value="opt.value"
        :title="opt.title"
      >
        {{ opt.label }}
      </option>
    </select>
  </div>
</template>
