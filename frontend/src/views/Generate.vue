<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { postGenerate } from '@/api/client'

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

const API_KEY_STORAGE = 'moviemind_api_key'

onMounted(() => {
  const stored = localStorage.getItem(API_KEY_STORAGE)
  if (stored) apiKey.value = stored
})

const entityTypes = [
  { value: 'MOVIE', label: 'Movie' },
  { value: 'PERSON', label: 'Person' },
  { value: 'TV_SERIES', label: 'TV Series' },
  { value: 'TV_SHOW', label: 'TV Show' },
]

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
    error.value = 'Slug or entity ID is required'
    return
  }
  if (!apiKey.value?.trim()) {
    error.value = 'API key is required for generation'
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
    if (route.query.save_key !== '0') {
      localStorage.setItem(API_KEY_STORAGE, apiKey.value.trim())
    }
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
      Generate description
    </h1>
    <p class="text-gray-600 mb-6">
      Queue AI generation for a movie, person, TV series, or TV show. Requires an API key.
    </p>

    <form
      v-if="!result || !result.job_id"
      class="space-y-4"
      @submit.prevent="submit"
    >
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Entity type</label>
        <select
          v-model="entityType"
          class="w-full rounded border border-gray-300 px-3 py-2"
        >
          <option
            v-for="opt in entityTypes"
            :key="opt.value"
            :value="opt.value"
          >
            {{ opt.label }}
          </option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Slug or entity ID</label>
        <input
          v-model="slug"
          type="text"
          placeholder="e.g. the-matrix-1999"
          class="w-full rounded border border-gray-300 px-3 py-2"
        >
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Locale</label>
        <select
          v-model="locale"
          class="w-full rounded border border-gray-300 px-3 py-2"
        >
          <option
            v-for="opt in locales"
            :key="opt.value"
            :value="opt.value"
          >
            {{ opt.label }}
          </option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Context tag</label>
        <select
          v-model="contextTag"
          class="w-full rounded border border-gray-300 px-3 py-2"
        >
          <option
            v-for="opt in contextTags"
            :key="opt.value"
            :value="opt.value"
          >
            {{ opt.label }}
          </option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">API key</label>
        <input
          v-model="apiKey"
          type="password"
          placeholder="Your MovieMind API key"
          class="w-full rounded border border-gray-300 px-3 py-2"
          autocomplete="off"
        >
      </div>
      <p
        v-if="error"
        class="text-red-600 text-sm"
      >
        {{ error }}
      </p>
      <button
        type="submit"
        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
        :disabled="loading"
      >
        {{ loading ? 'Submitting...' : 'Queue generation' }}
      </button>
    </form>

    <div
      v-else-if="result?.job_id"
      class="p-4 bg-green-50 rounded-lg"
    >
      <p class="text-green-800">
        Generation queued.
      </p>
      <router-link
        :to="{ name: 'Job', params: { id: result.job_id } }"
        class="text-indigo-600 hover:underline mt-2 inline-block"
      >
        View job status
      </router-link>
      <router-link
        v-if="detailRoute"
        :to="detailRoute"
        class="ml-4 text-indigo-600 hover:underline mt-2 inline-block"
      >
        View {{ result.entity }}: {{ result.slug }}
      </router-link>
    </div>
  </div>
</template>
