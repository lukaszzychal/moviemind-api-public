<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getJob } from '@/api/client'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
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
      {{ t('job.title') }}
    </h1>
    <div
      v-if="loading && !job"
      class="text-gray-500"
    >
      {{ t('job.loading') }}
    </div>
    <div
      v-else-if="error"
      class="p-4 bg-red-50 text-red-700 rounded-lg"
    >
      {{ error }}
    </div>
      <Card
        v-else-if="job"
        padding-class="p-6"
      >
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <span class="font-medium text-gray-700">{{ t('job.status_label') }}</span>
            <Badge
              :variant="job.status === 'DONE' ? 'success' : job.status === 'PENDING' ? 'warning' : job.status === 'FAILED' ? 'danger' : 'default'"
            >
              {{ t('job.status.' + job.status) }}
            </Badge>
          </div>
          <div v-if="job.entity" class="flex justify-between border-t border-gray-100 pt-3">
            <span class="font-medium text-gray-700">{{ t('job.entity_label') }}</span>
            <span class="text-gray-900 font-medium">{{ job.entity }} — {{ job.slug }}</span>
          </div>
          <div
            v-if="job.error"
            class="p-4 bg-red-50 border border-red-100 rounded-lg text-red-700 text-sm mt-4"
          >
            {{ typeof job.error === 'object' ? JSON.stringify(job.error) : job.error }}
          </div>
          <div
            v-if="job.status === 'DONE' && detailRoute"
            class="mt-6 pt-4 border-t border-gray-100 flex justify-end"
          >
            <Button
              @click="router.push(detailRoute)"
              variant="primary"
            >
              {{ t('job.view_entity') }} {{ job.entity }}
            </Button>
          </div>
        </div>
      </Card>
  </div>
</template>
