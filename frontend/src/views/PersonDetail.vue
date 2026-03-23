<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getPerson, getPersonRelated, reportPerson } from '@/api/client'
import ReportModal from '@/components/ReportModal.vue'
import Select from '@/components/ui/Select.vue'
import Button from '@/components/ui/Button.vue'
import { useI18n } from 'vue-i18n'
import { formatDate } from '@/composables/useDate.js'

const { t, locale } = useI18n()

const route = useRoute()
const router = useRouter()
const slug = computed(() => route.params.slug)
const bioId = computed(() => route.query.bio_id || null)
const person = ref(null)
const acceptedGeneration = ref(null)
const related = ref(null)
const loading = ref(true)
const error = ref(null)
const reportOpen = ref(false)

async function loadPerson () {
  if (!slug.value) return
  loading.value = true
  error.value = null
  person.value = null
  acceptedGeneration.value = null
  try {
    const data = await getPerson(slug.value)
    if (data.job_id && data.status === 'PENDING') {
      acceptedGeneration.value = data
      person.value = null
    } else {
      person.value = data
      acceptedGeneration.value = null
    }
  } catch (e) {
    error.value = e.data?.message || e.message || 'Failed to load person'
  } finally {
    loading.value = false
  }
}

async function loadRelated () {
  if (!slug.value) return
  try {
    related.value = await getPersonRelated(slug.value)
  } catch {
    related.value = null
  }
}

watch([slug, locale], () => {
  loadPerson()
  loadRelated()
}, { immediate: true })

const selectedBio = computed(() => {
  const p = person.value
  if (!p) return null
  if (bioId.value && p.bios) {
    const found = p.bios.find(b => String(b.id) === String(bioId.value))
    if (found) return found
  }
  return p.default_bio || (p.bios && p.bios[0]) || null
})

function selectBio (id) {
  router.replace({ query: { ...route.query, bio_id: id } })
}

async function onReport (payload) {
  const { description_id, ...rest } = payload
  await reportPerson(slug.value, {
    ...rest,
    bio_id: selectedBio.value?.id,
  })
}

const relatedList = computed(() => {
  const r = related.value
  return r?.related_people ?? r?.people ?? []
})

const formattedBirthDate = computed(() => {
  if (!person.value?.birth_date) return null
  return formatDate(person.value.birth_date, locale.value)
})
</script>

<template>
  <div>
    <div
      v-if="loading"
      class="text-gray-500"
    >
      {{ t('detail.loading') }}
    </div>
    <div
      v-else-if="error"
      class="p-4 bg-red-50 text-red-700 rounded-lg"
    >
      {{ error }}
    </div>
    <div
      v-else-if="acceptedGeneration"
      class="p-4 bg-amber-50 rounded-lg"
    >
      <p class="text-amber-800">
        {{ t('detail.generating_bio') }}
      </p>
      <router-link
        :to="{ name: 'Job', params: { id: acceptedGeneration.job_id } }"
        class="text-indigo-600 hover:underline mt-2 inline-block"
      >
        {{ t('detail.check_job') }}
      </router-link>
    </div>
    <template v-else-if="person">
      <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">
          {{ person.name }}
        </h1>
        <p
          v-if="formattedBirthDate"
          class="text-gray-600 mt-1"
        >
          {{ formattedBirthDate }}
          <span v-if="person.birthplace"> · {{ person.birthplace }}</span>
        </p>
      </div>

      <div
        v-if="person.bios && person.bios.length > 1"
        class="mb-4"
      >
        <Select
          :label="t('detail.bio_version')"
          :model-value="selectedBio?.id"
          @update:model-value="selectBio"
          :options="person.bios.map(b => ({
            value: b.id,
            label: `${b.locale} ${b.context_tag ? `(${b.context_tag})` : ''}`
          }))"
        />
      </div>

      <div
        v-if="selectedBio"
        class="prose max-w-none mb-8"
      >
        <p class="text-gray-700 whitespace-pre-wrap">
          {{ selectedBio.text }}
        </p>
      </div>

      <div
        v-if="person.movies && person.movies.length"
        class="mb-8"
      >
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          {{ t('detail.movies') }}
        </h2>
        <ul class="flex flex-wrap gap-2">
          <li
            v-for="m in person.movies"
            :key="m.slug"
          >
            <router-link
              :to="{ name: 'MovieDetail', params: { slug: m.slug } }"
              class="text-indigo-600 hover:underline"
            >
              {{ m.title }}
              <span
                v-if="m.release_year"
                class="text-gray-500"
              >({{ m.release_year }})</span>
            </router-link>
          </li>
        </ul>
      </div>

      <div
        v-if="relatedList.length"
        class="mb-8"
      >
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          {{ t('detail.related_people') }}
        </h2>
        <ul class="space-y-1">
          <li
            v-for="r in relatedList"
            :key="r.slug"
          >
            <router-link
              :to="{ name: 'PersonDetail', params: { slug: r.slug } }"
              class="text-indigo-600 hover:underline"
            >
              {{ r.name }}
            </router-link>
          </li>
        </ul>
      </div>

      <div class="flex flex-wrap gap-4 mt-8">
        <Button
          @click="router.push({ name: 'Generate', query: { entity_type: 'PERSON', slug: person.slug } })"
          variant="primary"
        >
          {{ t('detail.generate_bio') }}
        </Button>
        <Button
          @click="router.push({ name: 'Compare', query: { type: 'people', slug1: person.slug } })"
          variant="outline"
        >
          {{ t('detail.compare') }}
        </Button>
        <Button
          @click="reportOpen = true"
          variant="outline"
        >
          {{ t('detail.report') }}
        </Button>
      </div>
    </template>

    <ReportModal
      v-model="reportOpen"
      :description-id="selectedBio?.id"
      :on-submit="onReport"
    />
  </div>
</template>
