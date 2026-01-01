<template>
  <Card class="h-full flex flex-col">
    <template #header>
      <div class="font-semibold flex items-center gap-2">
        <span class="text-xl">❤️</span> Server Pulse
      </div>
      <div class="text-xs text-muted-foreground mt-1">
        Real-time system resource monitoring
      </div>
    </template>
    <div class="flex-1 space-y-6 pt-2">
      <!-- CPU Load -->
      <div class="space-y-1">
        <div class="flex justify-between text-sm">
          <span>CPU Load</span>
          <span class="font-medium font-mono">{{ data.cpu_load }}</span>
        </div>
        <!-- CPU load isn't percentage based usually, but we can just show a visual indicator or skip bar -->
        <div class="h-2 w-full bg-secondary rounded-full overflow-hidden">
          <div
            class="h-full bg-primary transition-all"
            :style="{ width: Math.min(data.cpu_load * 20, 100) + '%' }"
          ></div>
        </div>
      </div>

      <!-- Disk Usage -->
      <div class="space-y-1">
        <div class="flex justify-between text-sm">
          <span>Disk Usage</span>
          <span
            class="font-medium"
            :class="{ 'text-red-500': data.disk_percent > 90 }"
            >{{ data.disk_percent }}%</span
          >
        </div>
        <div class="h-2 w-full bg-secondary rounded-full overflow-hidden">
          <div
            class="h-full transition-all"
            :class="data.disk_percent > 90 ? 'bg-red-500' : 'bg-primary'"
            :style="{ width: data.disk_percent + '%' }"
          ></div>
        </div>
      </div>

      <!-- RAM Usage -->
      <div class="space-y-1">
        <div class="flex justify-between text-sm">
          <span>RAM Usage</span>
          <span
            class="font-medium"
            :class="{ 'text-red-500': data.ram_percent > 90 }"
            >{{ data.ram_percent }}%</span
          >
        </div>
        <div class="h-2 w-full bg-secondary rounded-full overflow-hidden">
          <div
            class="h-full transition-all"
            :class="data.ram_percent > 90 ? 'bg-red-500' : 'bg-primary'"
            :style="{ width: data.ram_percent + '%' }"
          ></div>
        </div>
      </div>
    </div>
  </Card>
</template>

<script setup>
import Card from "../ui/Card.vue";

defineProps({
  data: {
    type: Object,
    default: () => ({ disk_percent: 0, ram_percent: 0, cpu_load: 0 }),
  },
});
</script>
