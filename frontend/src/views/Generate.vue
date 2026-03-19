<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getRootWelcome, postGenerate } from '@/api/client'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const entityType = ref(route.query.entity_type || 'MOVIE')
const slug = ref(route.query.slug || '')
const locale = ref(route.query.locale || 'en-US')
const contextTag = ref(route.query.context_tag || 'modern')
const apiKey = ref('')
const loading = ref(false)
const error = ref(null)
const result = ref(null)

// Antispam "Human" validation for public demo key
const isHuman = ref(false)
const obtainingDemo = ref(false)
const demoNote = ref('')

async function getDemoKey() {
  if (!isHuman.value) {
    error.value = t('generate.error.not_human')
    return;
  }
  
  error.value = null;
  obtainingDemo.value = true;
  
  try {
    const data = await getRootWelcome();
    if (data && data.demo && data.demo.api_key) {
      apiKey.value = data.demo.api_key;
      demoNote.value = data.demo.note || `Loaded demo key (Plan: ${data.demo.plan})`;
    } else {
      error.value = t('generate.error.demo_unavailable')
    }
  } catch (err) {
    error.value = 'Failed to load demo key: ' + (err.message || 'Unknown error');
  } finally {
    obtainingDemo.value = false;
  }
}

// Local storage removed due to security alert: Clear text storage of sensitive information

const entityTypes = computed(() => [
  { value: 'MOVIE', label: t('types.movies') },
  { value: 'PERSON', label: t('types.people') },
  { value: 'TV_SERIES', label: t('types.tv_series') },
  { value: 'TV_SHOW', label: t('types.tv_shows') },
])

const locales = [
  { value: 'en-US', label: 'English (US)' },
  { value: 'pl-PL', label: 'Polish' },
  { value: 'de-DE', label: 'German' },
  { value: 'fr-FR', label: 'French' },
  { value: 'es-ES', label: 'Spanish' },
]

const contextTags = [
  { value: 'modern', label: 'Modern' },
  { value: 'critical', label: 'Critical' },
  { value: 'humorous', label: 'Humorous' },
  { value: 'DEFAULT', label: 'Default' },
]

async function submit () {
  const s = slug.value?.trim()
  if (!s) {
    error.value = t('generate.error.no_slug')
    return
  }
  if (!apiKey.value?.trim()) {
    error.value = t('generate.error.no_api_key')
    return
  }
  error.value = null
  result.value = null
  loading.value = true
  try {
    const body = {
      entity_type: entityType.value,
      slug: s,
      locale: locale.value,
      context_tag: contextTag.value,
    }
    const data = await postGenerate(body, apiKey.value.trim())
// persistence removed for security reasons
    result.value = data
    if (data.job_id) {
      router.push({ name: 'Job', params: { id: data.job_id } })
    }
  } catch (e) {
    error.value = e.data?.message || e.message || 'Generation request failed'
  } finally {
    loading.value = false
  }
}

const detailRoute = computed(() => {
  const s = result.value?.slug
  if (!s) return null
  switch (result.value?.entity) {
    case 'MOVIE':
      return { name: 'MovieDetail', params: { slug: s } }
    case 'PERSON':
      return { name: 'PersonDetail', params: { slug: s } }
    case 'TV_SERIES':
      return { name: 'TvSeriesDetail', params: { slug: s } }
    case 'TV_SHOW':
      return { name: 'TvShowDetail', params: { slug: s } }
    default:
      return { name: 'MovieDetail', params: { slug: s } }
  }
})
</script>

<template>
  <div class="max-w-xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-4">
      {{ t('generate.title') }}
    </h1>
    <p class="text-gray-600 mb-6">
      {{ t('generate.subtitle') }}
    </p>

    <form
      v-if="!result || !result.job_id"
      class="space-y-4"
      @submit.prevent="submit"
    >
      <Select
        :label="t('generate.entity_type_label')"
        v-model="entityType"
        :options="entityTypes"
      />
      <Input
        :label="t('generate.slug_label')"
        v-model="slug"
        :placeholder="t('generate.slug_placeholder')"
      />
      <Select
        :label="t('generate.locale_label')"
        v-model="locale"
        :options="locales"
      />
      <Select
        :label="t('generate.context_tag_label')"
        v-model="contextTag"
        :options="contextTags"
      />
      <div class="pt-2 border-t border-gray-200 mt-4">
        <Input
          :label="t('generate.api_key_label')"
          v-model="apiKey"
          type="password"
          :placeholder="t('generate.api_key_placeholder')"
          autocomplete="off"
        />
        
        <div class="mt-4 p-4 bg-gray-50/80 border border-gray-200 rounded-xl text-sm text-gray-700 hover:shadow-sm transition-all">
          <div class="flex items-center justify-between gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="isHuman" class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4">
              <span class="text-gray-600 font-medium tracking-tight">{{ t('generate.iam_human') }}</span>
            </label>
            <Button
              type="button"
              variant="outline"
              size="sm"
              @click="getDemoKey"
              :disabled="!isHuman || obtainingDemo"
            >
              {{ obtainingDemo ? t('generate.loading_demo') : t('generate.get_demo_key') }}
            </Button>
          </div>
          <p v-if="demoNote" class="mt-3 text-xs text-indigo-600 font-medium">
            ⚠️ {{ demoNote }}
          </p>
        </div>
      </div>
      <p
        v-if="error"
        class="text-red-600 text-sm"
      >
        {{ error }}
      </p>
      <Button
        type="submit"
        variant="primary"
        block
        :disabled="loading"
        :loading="loading"
      >
        {{ loading ? t('generate.submitting') : t('generate.submit') }}
      </Button>
    </form>

    <Card
      v-else-if="result?.job_id"
      class="bg-green-50/50 border-green-100"
    >
      <div class="flex items-center gap-3">
        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-green-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        </div>
        <div>
          <h3 class="text-green-800 font-bold text-lg mb-1">
            {{ t('generate.success_title') }}
          </h3>
          <div class="flex gap-4 text-sm font-medium">
            <router-link
              :to="{ name: 'Job', params: { id: result.job_id } }"
              class="text-indigo-600 hover:text-indigo-800 transition-colors"
            >
              View job status &rarr;
            </router-link>
            <router-link
              v-if="detailRoute"
              :to="detailRoute"
              class="text-indigo-600 hover:text-indigo-800 transition-colors"
            >
              View {{ result.entity }}: {{ result.slug }} &rarr;
            </router-link>
          </div>
        </div>
      </div>
    </Card>
  </div>
</template>
