<template>
  <Card class="h-full flex flex-col relative overflow-hidden group">
    <div
      class="absolute inset-0 bg-gradient-to-br from-transparent to-muted/20 opacity-0 group-hover:opacity-100 transition-opacity"
    ></div>
    <template #header>
      <div class="font-semibold flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="text-xl">🛡️</span> Backup Status
        </div>
        <span
          class="px-2 py-0.5 rounded-full text-xs font-medium uppercase tracking-wide"
          :class="
            isSafe ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
          "
        >
          {{ isSafe ? "Protected" : "At Risk" }}
        </span>
      </div>
    </template>

    <div class="flex-1 flex flex-col justify-center py-4 relative z-10">
      <div class="flex items-center gap-4">
        <!-- Modern Status Ring -->
        <div class="relative w-16 h-16 flex-shrink-0">
          <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
            <!-- Background Circle -->
            <path
              class="text-muted/30"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
              fill="none"
              stroke="currentColor"
              stroke-width="3"
            />
            <!-- Progress Circle -->
            <path
              :class="isSafe ? 'text-green-500' : 'text-red-500'"
              :stroke-dasharray="isSafe ? '100, 100' : '75, 100'"
              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
              fill="none"
              stroke="currentColor"
              stroke-width="3"
            />
          </svg>
          <div
            class="absolute inset-0 flex items-center justify-center text-xl"
          >
            {{ isSafe ? "✓" : "!" }}
          </div>
        </div>

        <div class="flex-1 min-w-0">
          <div class="text-sm text-muted-foreground">Latest Snapshot</div>
          <div class="font-medium text-foreground truncate text-lg">
            {{ data.last_run || "Never" }}
          </div>
          <div
            class="text-xs text-muted-foreground mt-1 flex items-center gap-1"
          >
            <span
              class="w-1.5 h-1.5 rounded-full"
              :class="isSafe ? 'bg-green-500' : 'bg-red-500'"
            ></span>
            {{ isSafe ? "Database secure" : "Run backup immediately" }}
          </div>
        </div>
      </div>
    </div>
  </Card>
</template>

<script setup>
import { computed } from "vue";
import Card from "../ui/Card.vue";

const props = defineProps({
  data: {
    type: Object,
    default: () => ({ status: "warning", last_run: "Never" }),
  },
});

const isSafe = computed(() => props.data.status === "safe");
</script>
