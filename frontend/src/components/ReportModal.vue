<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  descriptionId: { type: String, default: null },
  /** Parent provides (payload) => Promise; called on submit */
  onSubmit: { type: Function, required: true },
})
const emit = defineEmits(['update:modelValue'])

const type = ref('other')
const message = ref('')
const suggestedFix = ref('')
const sending = ref(false)
const error = ref(null)

const reportTypes = [
  { value: 'incorrect_info', label: 'Incorrect info' },
  { value: 'grammar_error', label: 'Grammar error' },
  { value: 'factual_error', label: 'Factual error' },
  { value: 'incomplete', label: 'Incomplete' },
  { value: 'inappropriate', label: 'Inappropriate' },
  { value: 'other', label: 'Other' },
]

watch(() => props.modelValue, (open) => {
  if (!open) {
    message.value = ''
    suggestedFix.value = ''
    error.value = null
  }
})

async function submit () {
  if (!message.value.trim() || message.value.length < 10) {
    error.value = 'Message must be at least 10 characters.'
    return
  }
  error.value = null
  sending.value = true
  try {
    const payload = {
      type: type.value,
      message: message.value.trim(),
      suggested_fix: suggestedFix.value.trim() || undefined,
      description_id: props.descriptionId || undefined,
    }
    await props.onSubmit(payload)
    emit('update:modelValue', false)
  } catch (e) {
    error.value = e.data?.message || e.message || 'Report failed'
  } finally {
    sending.value = false
  }
}

</script>

<template>
  <div
    v-if="modelValue"
    class="fixed inset-0 z-10 flex items-center justify-center bg-black/50"
    @click.self="$emit('update:modelValue', false)"
  >
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">
        Report issue
      </h3>
      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
          <select
            v-model="type"
            class="w-full rounded border border-gray-300 px-3 py-2"
          >
            <option
              v-for="opt in reportTypes"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Message (min 10 chars)</label>
          <textarea
            v-model="message"
            rows="4"
            class="w-full rounded border border-gray-300 px-3 py-2"
            required
          />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Suggested fix (optional)</label>
          <textarea
            v-model="suggestedFix"
            rows="2"
            class="w-full rounded border border-gray-300 px-3 py-2"
          />
        </div>
        <p
          v-if="error"
          class="text-red-600 text-sm mb-4"
        >
          {{ error }}
        </p>
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="px-4 py-2 border border-gray-300 rounded-lg"
            @click="$emit('update:modelValue', false)"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
            :disabled="sending"
          >
            {{ sending ? 'Sending...' : 'Send report' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
