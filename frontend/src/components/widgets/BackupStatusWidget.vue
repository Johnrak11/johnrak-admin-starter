<template>
  <Card class="h-full flex flex-col">
    <template #header>
      <div class="font-semibold flex items-center gap-2">
        <span class="text-xl">💾</span> Backup Status
      </div>
    </template>
    <div class="flex-1 flex flex-col justify-center items-center py-4">
      <div
        class="w-16 h-16 rounded-full flex items-center justify-center text-3xl mb-3"
        :class="data.ran_today ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'"
      >
        {{ data.ran_today ? '✓' : '!' }}
      </div>
      <div class="text-center">
        <div class="font-medium">{{ data.ran_today ? 'Backup Successful' : 'No Backup Today' }}</div>
        <div class="text-sm text-muted-foreground mt-1">
          Last run: {{ data.last_run || 'Never' }}
        </div>
        <div class="text-xs text-muted-foreground mt-0.5 uppercase tracking-wider">
          {{ data.disk }}
        </div>
      </div>
    </div>
  </Card>
</template>

<script setup>
import Card from '../ui/Card.vue'

defineProps({
  data: Object
})
</script>
