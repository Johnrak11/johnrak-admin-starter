<template>
  <Card class="h-full flex flex-col">
    <template #header>
      <div class="font-semibold flex items-center gap-2">
        <span class="text-xl">🧠</span> AI Quick Action
      </div>
    </template>
    <div class="flex-1 flex flex-col gap-3 pt-2">
      <textarea
        v-model="prompt"
        class="flex-1 w-full min-h-[100px] rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring resize-none"
        placeholder="Log an idea or ask a question..."
      ></textarea>
      <div class="flex justify-end">
        <button
          @click="send"
          :disabled="loading || !prompt"
          class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-9 px-4 py-2"
        >
          {{ loading ? 'Sending...' : 'Send to AI' }}
        </button>
      </div>
      <div v-if="response" class="mt-2 p-3 bg-muted/50 rounded-lg text-sm text-muted-foreground whitespace-pre-wrap max-h-32 overflow-y-auto">
        {{ response }}
      </div>
    </div>
  </Card>
</template>

<script setup>
import { ref } from 'vue'
import { api } from '../../lib/api'
import Card from '../ui/Card.vue'

const prompt = ref('')
const loading = ref(false)
const response = ref('')

async function send() {
  if (!prompt.value) return
  loading.value = true
  response.value = ''
  try {
    const res = await api().post('/api/ai/chat', { message: prompt.value })
    response.value = res.data.assistant
    prompt.value = '' // clear on success
    // Optional: emit event to open full chat
  } catch (e) {
    alert('Failed to send')
  } finally {
    loading.value = false
  }
}
</script>
