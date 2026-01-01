<template>
  <Card class="h-full flex flex-col">
    <template #header>
      <div class="font-semibold flex items-center gap-2">
        <span class="text-xl">🖥️</span> System Resources
      </div>
      <div class="text-xs text-muted-foreground mt-1">
        Monitoring local environment (Docker/VPS)
      </div>
    </template>
    <div class="flex-1 space-y-4 pt-2">
      <div class="space-y-1">
        <div class="flex justify-between text-sm">
          <span>Disk Usage</span>
          <span class="font-medium">{{ data.disk_usage_pct }}%</span>
        </div>
        <Progress :value="data.disk_usage_pct" />
      </div>

      <div class="space-y-1">
        <div class="flex justify-between text-sm">
          <span>RAM Usage</span>
          <span class="font-medium">{{ data.ram_usage_pct }}%</span>
        </div>
        <Progress :value="data.ram_usage_pct" />
      </div>
    </div>
  </Card>
</template>

<script setup>
import Card from '../ui/Card.vue'
import Progress from '../ui/Progress.vue'

defineProps({
  data: Object
})
</script>
