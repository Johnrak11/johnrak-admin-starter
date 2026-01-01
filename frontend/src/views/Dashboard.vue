<template>
  <div class="space-y-6">
    <div
      v-if="loading"
      class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6"
    >
      <Skeleton className="h-48 w-full" />
      <Skeleton className="h-48 w-full" />
      <Skeleton className="h-48 w-full" />
    </div>

    <div v-else-if="activeWidgets.length === 0" class="text-center py-12">
      <div class="text-4xl mb-4">📭</div>
      <h3 class="text-lg font-medium">Dashboard is empty</h3>
      <p class="text-muted-foreground max-w-sm mx-auto mt-2">
        No active widgets found. Please run the database seeder to initialize
        the dashboard.
      </p>
      <div
        class="mt-4 p-4 bg-muted/50 rounded-lg font-mono text-xs inline-block text-left"
      >
        php artisan migrate<br />
        php artisan db:seed --class=DashboardWidgetSeeder
      </div>
    </div>

    <div
      v-else
      class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 items-stretch"
    >
      <div
        v-for="widget in activeWidgets"
        :key="widget.id"
        :class="getColSpan(widget.width)"
        class="h-full"
      >
        <component
          :is="resolveComponent(widget.component_name)"
          :data="getWidgetData(widget.component_name)"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, computed } from "vue";
import { api } from "../lib/api";
import Skeleton from "../components/ui/Skeleton.vue";
import ServerHealthWidget from "../components/widgets/ServerHealthWidget.vue";
import AiQuickActionWidget from "../components/widgets/AiQuickActionWidget.vue";
import BackupStatusWidget from "../components/widgets/BackupStatusWidget.vue";

const loading = ref(true);
const widgets = ref([]);
const systemHealth = ref({});
const backupStatus = ref({});

const componentMap = {
  ServerHealthWidget,
  AiQuickActionWidget,
  BackupStatusWidget,
};

const activeWidgets = computed(() => {
  return widgets.value.filter((w) => w.is_active);
});

function resolveComponent(name) {
  return componentMap[name] || null;
}

function getColSpan(width) {
  // Map 1-4 to tailwind classes
  const map = {
    1: "col-span-1",
    2: "col-span-1 md:col-span-2",
    3: "col-span-1 md:col-span-3",
    4: "col-span-1 md:col-span-4",
  };
  return map[width] || "col-span-1";
}

function getWidgetData(name) {
  if (name === "ServerHealthWidget") return systemHealth.value;
  if (name === "BackupStatusWidget") return backupStatus.value;
  return {};
}

async function load() {
  loading.value = true;
  try {
    const res = await api().get("/api/dashboard/summary");
    widgets.value = res.data.widgets || [];
    systemHealth.value = res.data.system_health || {};
    backupStatus.value = res.data.backup_status || {};
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>
