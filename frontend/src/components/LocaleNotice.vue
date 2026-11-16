<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { contentLocaleFor, languageName } from '@/utils/localizedText'

const props = defineProps({
  contentLocale: { type: String, required: true },
  entityType: { type: String, required: true },
  slug: { type: String, required: true },
})

const { t, locale } = useI18n()

const wantedLocale = computed(() => contentLocaleFor(locale.value))
const textLanguage = computed(() => languageName(props.contentLocale, locale.value))
const wantedLanguage = computed(() => languageName(wantedLocale.value, locale.value))
const generateRoute = computed(() => ({
  name: 'Generate',
  query: { entity_type: props.entityType, slug: props.slug, locale: wantedLocale.value },
}))
</script>

<template>
  <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">
    <span>{{ t('detail.other_language', { language: textLanguage }) }}</span>
    <router-link
      :to="generateRoute"
      class="font-medium text-indigo-600 hover:underline"
    >
      {{ t('detail.generate_in_language', { language: wantedLanguage }) }}
    </router-link>
  </div>
</template>
