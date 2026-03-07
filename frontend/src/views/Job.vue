<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getJob } from '@/api/client'

const route = useRoute()
const router = useRouter()
const id = computed(() => route.params.id)
const job = ref(null)
const loading = ref(true)
const error = ref(null)
let pollTimer = null

async function load () {
  if (!id.value) return
  loading.value = true
  error.value = null
  try {
    job.value = await getJob(id.value)
  } catch (e) {
    error.value = e.data?.message || e.message || 'Failed to load job'
    job.value = null
  } finally {
    loading.value = false
  }
}

function startPolling () {
  if (pollTimer) return
  pollTimer = setInterval(() => {
    if (job.value?.status === 'PENDING') {
      load()
    } else {
      stopPolling()
    }
  }, 3000)
}

function stopPolling () {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

watch(id, load, { immediate: true })
watch(job, (j) => {
  if (j?.status === 'PENDING') startPolling()
  else stopPolling()
}, { immediate: true })

onUnmounted(stopPolling)

const detailRoute = computed(() => {
  const j = job.value
  if (!j?.slug) return null
  switch (j.entity) {
    case 'MOVIE':
      return { name: 'MovieDetail', params: { slug: j.slug } }
    case 'PERSON':
      return { name: 'PersonDetail', params: { slug: j.slug } }
    case 'TV_SERIES':
      return { name: 'TvSeriesDetail', params: { slug: j.slug } }
    case 'TV_SHOW':
      return { name: 'TvShowDetail', params: { slug: j.slug } }
    default:
      return { name: 'MovieDetail', params: { slug: j.slug } }
  }
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900 mb-4">
      Job status
    </h1>
    <div
      v-if="loading && !job"
      class="text-gray-500"
    >
      Loading...
    </div>
    <div
      v-else-if="error"
      class="p-4 bg-red-50 text-red-700 rounded-lg"
    >
      {{ error }}
    </div>
    <div
      v-else-if="job"
      class="space-y-2"
    >
      <p>
        <span class="font-medium text-gray-700">Status:</span>
        <span
          :class="{
            'text-amber-600': job.status === 'PENDING',
            'text-green-600': job.status === 'DONE',
            'text-red-600': job.status === 'FAILED',
            'text-gray-600': job.status === 'UNKNOWN',
          }"
        >
          {{ job.status }}
        </span>
      </p>
      <p v-if="job.entity">
        <span class="font-medium text-gray-700">Entity:</span> {{ job.entity }} — {{ job.slug }}
      </p>
      <div
        v-if="job.error"
        class="p-3 bg-red-50 rounded text-red-700 text-sm"
      >
        {{ typeof job.error === 'object' ? JSON.stringify(job.error) : job.error }}
      </div>
      <div
        v-if="job.status === 'DONE' && detailRoute"
        class="mt-4"
      >
        <router-link
          :to="detailRoute"
          class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
        >
          View {{ job.entity }}
        </router-link>
      </div>
    </div>
  </div>
</template>
