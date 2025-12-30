<template>
  <div
    v-if="show"
    class="fixed bottom-4 right-4 max-w-md rounded-xl border border-border bg-card px-4 py-3 text-sm text-card-foreground shadow-lg"
  >
    <div class="flex items-start gap-3">
      <div class="mt-1 h-2 w-2 rounded-full bg-primary"></div>
      <div class="flex-1">
        <div class="font-medium">{{ title }}</div>
        <div class="mt-1 text-muted-foreground">{{ message }}</div>
      </div>
      <button
        type="button"
        class="rounded-md text-muted-foreground transition hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
        @click="$emit('close')"
      >
        ✕
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  show: Boolean,
  title: { type: String, default: 'Info' },
  message: { type: String, default: '' }
})
defineEmits(['close'])
</script>
