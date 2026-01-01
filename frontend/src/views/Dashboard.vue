<template>
  <div class="space-y-6">
    <!-- Loading State -->
    <div
      v-if="loading"
      class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
    >
      <Skeleton className="h-64 w-full" />
      <Skeleton className="h-64 w-full" />
      <Skeleton className="h-64 w-full" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="activeWidgets.length === 0"
      class="text-center py-12 border border-dashed border-border rounded-xl"
    >
      <div class="text-4xl mb-4">📭</div>
      <h3 class="text-lg font-medium">Mission Control Empty</h3>
      <p class="text-muted-foreground max-w-sm mx-auto mt-2">
        No active widgets found.
      </p>
    </div>

    <!-- Dashboard Grid -->
    <div
      v-else
      class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 items-start"
    >
      <div v-for="widget in activeWidgets" :key="widget.id" class="h-full">
        <component
          :is="resolveComponent(widget.component)"
          :data="getWidgetData(widget.component)"
        />
      </div>
    </div>

    <!-- Database Size Indicator (Extra) -->
    <div v-if="dbSize" class="text-xs text-muted-foreground text-right mt-4">
      Database Size: {{ dbSize }}
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, computed } from "vue";
import { api } from "../lib/api";
import Skeleton from "../components/ui/Skeleton.vue";

// Import Widgets
import WidgetServerHealth from "../components/widgets/WidgetServerHealth.vue";
import WidgetBackupStatus from "../components/widgets/WidgetBackupStatus.vue";
import WidgetQuickAi from "../components/widgets/WidgetQuickAi.vue";

const loading = ref(true);
const widgets = ref([]);
const serverData = ref({});
const backupData = ref({});
const dbSize = ref(null);

const componentMap = {
  WidgetServerHealth,
  WidgetBackupStatus,
  WidgetQuickAi,
};

const activeWidgets = computed(() => {
  return widgets.value.filter((w) => w.is_active);
});

function resolveComponent(name) {
  return componentMap[name] || null;
}

function getWidgetData(name) {
  if (name === "WidgetServerHealth") return serverData.value;
  if (name === "WidgetBackupStatus") return backupData.value;
  return {};
}

async function load() {
  loading.value = true;
  try {
    const res = await api().get("/api/dashboard");
    widgets.value = res.data.widgets || [];
    serverData.value = res.data.data.server || {};
    backupData.value = res.data.data.backup || {};
    dbSize.value = res.data.data.database_size;
  } catch (e) {
    console.error("Dashboard load error", e);
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>
