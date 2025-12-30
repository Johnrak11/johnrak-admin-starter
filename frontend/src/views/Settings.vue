<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <Card>
        <template #header>
          <div class="text-lg font-semibold">Theme</div>
          <div class="text-sm text-muted-foreground">
            Choose your preferred appearance.
          </div>
        </template>

        <div class="flex items-center justify-between">
          <div>
            <div class="text-sm font-medium">Dark mode</div>
            <div class="text-xs text-muted-foreground">
              Toggle between light and dark.
            </div>
          </div>
          <Switch v-model="isDark" />
        </div>
      </Card>

      <Card>
        <template #header>
          <div class="text-lg font-semibold">Config</div>
          <div class="text-sm text-muted-foreground">
            UI preferences for your dashboard.
          </div>
        </template>

        <div class="space-y-4">
          <div class="flex items-center justify-between gap-4">
            <div>
              <div class="text-sm font-medium">Compact sidebar</div>
              <div class="text-xs text-muted-foreground">
                Reduce sidebar width.
              </div>
            </div>
            <Switch
              :model-value="settings.compactSidebar"
              @update:model-value="settings.setCompactSidebar"
            />
          </div>

          <div class="flex items-center justify-between gap-4">
            <div>
              <div class="text-sm font-medium">Show hints</div>
              <div class="text-xs text-muted-foreground">
                Display small helper text in layouts.
              </div>
            </div>
            <Switch
              :model-value="settings.showHints"
              @update:model-value="settings.setShowHints"
            />
          </div>

          <div class="rounded-lg border border-border bg-background p-3">
            <div class="text-xs text-muted-foreground">API Base URL</div>
            <div class="mt-1 break-all font-mono text-sm">
              {{ apiBaseUrl }}
            </div>
          </div>

          <Button variant="ghost" class="w-full" @click="settings.reset">
            Reset settings
          </Button>
        </div>
      </Card>
    </div>

    <Card>
      <template #header>
        <div class="text-lg font-semibold">Theme color</div>
        <div class="text-sm text-muted-foreground">
          Pick an accent color used by buttons and focus rings.
        </div>
      </template>

      <div class="flex flex-wrap gap-2">
        <button
          v-for="c in accents"
          :key="c.key"
          type="button"
          class="group inline-flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-sm transition hover:bg-accent hover:text-accent-foreground"
          :class="settings.accent === c.key ? 'ring-2 ring-ring ring-offset-2 ring-offset-background' : ''"
          @click="settings.setAccent(c.key)"
        >
          <span
            class="h-4 w-4 rounded-full border border-border"
            :style="{ backgroundColor: c.preview }"
          />
          <span class="capitalize">{{ c.key }}</span>
        </button>
      </div>
    </Card>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Switch from '../components/ui/Switch.vue'
import { useSettingsStore } from '../stores/settings'

const settings = useSettingsStore()

const isDark = computed({
  get: () => settings.mode === 'dark',
  set: (v) => settings.setMode(v ? 'dark' : 'light')
})

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL

const accents = [
  { key: 'slate', preview: 'hsl(222.2 47.4% 11.2%)' },
  { key: 'blue', preview: 'hsl(221.2 83.2% 53.3%)' },
  { key: 'emerald', preview: 'hsl(142.1 76.2% 36.3%)' },
  { key: 'violet', preview: 'hsl(262.1 83.3% 57.8%)' },
  { key: 'rose', preview: 'hsl(346.8 77.2% 49.8%)' }
]
</script>

