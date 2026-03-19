<script setup>
import { ref } from 'vue'
import { postFeedback } from '@/api/client'
import { useI18n } from 'vue-i18n'
import { computed } from 'vue'

const { t } = useI18n()
const message = ref('')
const category = ref('other')
const sending = ref(false)
const error = ref(null)
const success = ref(false)

const categories = computed(() => [
  { value: 'bug', label: t('feedback.category.bug') },
  { value: 'suggestion', label: t('feedback.category.suggestion') },
  { value: 'other', label: t('feedback.category.other') },
])

async function submit () {
  const msg = message.value?.trim()
  if (!msg || msg.length < 10) {
    error.value = t('feedback.error.min_length')
    return
  }
  error.value = null
  success.value = false
  sending.value = true
  try {
    await postFeedback({ message: msg, category: category.value })
    success.value = true
    message.value = ''
  } catch (e) {
    error.value = e.data?.message || e.message || 'Failed to send feedback'
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="max-w-xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-4">
      {{ t('feedback.title') }}
    </h1>
    <p class="text-gray-600 mb-6">
      {{ t('feedback.subtitle') }}
    </p>

    <form
      v-if="!success"
      class="space-y-4"
      @submit.prevent="submit"
    >
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('feedback.category_label') }}</label>
        <select
          v-model="category"
          class="w-full rounded border border-gray-300 px-3 py-2"
        >
          <option
            v-for="opt in categories"
            :key="opt.value"
            :value="opt.value"
          >
            {{ opt.label }}
          </option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('feedback.message_label') }}</label>
        <textarea
          v-model="message"
          rows="5"
          class="w-full rounded border border-gray-300 px-3 py-2"
          required
        />
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
        :disabled="sending"
      >
        {{ sending ? t('feedback.sending') : t('feedback.send_button') }}
      </button>
    </form>

    <div
      v-else
      class="p-4 bg-green-50 text-green-800 rounded-lg"
    >
      {{ t('feedback.success') }}
    </div>
  </div>
</template>
