<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div>
          <div class="font-semibold">LinkedIn Import (JSON)</div>
          <div class="text-sm text-muted-foreground">
            Paste a JSON payload (supported format) and import.
          </div>
        </div>
      </template>

      <div class="space-y-3 text-sm text-muted-foreground">
        <div class="rounded-xl border border-border bg-muted/30 p-4">
          <div class="font-medium text-foreground">Example JSON</div>
          <pre class="mt-2 overflow-auto rounded-lg border border-border bg-muted/30 p-3 text-xs text-foreground">{{ example }}</pre>
        </div>

        <Label>Paste JSON</Label>
        <textarea
          v-model="jsonText"
          class="min-h-[220px] w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground
                 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
          placeholder="{ ... }"
        />

        <div class="flex items-center gap-2">
          <Button @click="importNow" :disabled="loading">{{ loading ? 'Importing...' : 'Import' }}</Button>
          <Button variant="ghost" @click="useExample">Use example</Button>
        </div>

        <div v-if="result" class="rounded-xl border border-border bg-muted/30 p-4">
          <div class="font-medium text-foreground">Result</div>
          <pre class="mt-2 overflow-auto text-xs text-foreground">{{ result }}</pre>
        </div>
      </div>
    </Card>

    <Toast :show="toast.show" :title="toast.title" :message="toast.message" @close="toast.show=false" />
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { api } from '../../lib/api'
import Card from '../../components/ui/Card.vue'
import Label from '../../components/ui/Label.vue'
import Button from '../../components/ui/Button.vue'
import Toast from '../../components/ui/Toast.vue'

const loading = ref(false)
const jsonText = ref('')
const result = ref('')

const toast = reactive({ show: false, title: 'Done', message: '' })

const exampleRaw = JSON.stringify({
  profile: {
    headline: "Full-stack Developer",
    summary: "I build modern web apps with Laravel + Vue.",
    location: "Phnom Penh, Cambodia",
    website_url: "https://johnrak.online",
    github_url: "https://github.com/johnrak",
    linkedin_url: "https://linkedin.com/in/johnrak"
  },
  experiences: [
    { company: "My Company", title: "Software Engineer", location: "Phnom Penh", employment_type: "Full-time",
      start_date: "2023-01-01", end_date: null, is_current: true, description: "Built dashboards.", sort_order: 0 }
  ],
  educations: [
    { school: "University", degree: "Bachelor", field_of_study: "Computer Science", start_date: "2018-01-01",
      end_date: "2022-01-01", description: "Graduated.", sort_order: 0 }
  ],
  skills: [
    { name: "Laravel", level: "Advanced", sort_order: 0 },
    { name: "Vue 3", level: "Advanced", sort_order: 1 }
  ],
  certifications: [
    { name: "Some Certificate", issuer: "Issuer", issue_date: "2024-01-01", expire_date: null,
      credential_id: "ABC-123", credential_url: "https://example.com", sort_order: 0 }
  ]
}, null, 2)

const example = computed(() => exampleRaw)

function useExample() {
  jsonText.value = exampleRaw
}

async function importNow() {
  loading.value = true
  result.value = ''
  try {
    const data = JSON.parse(jsonText.value)
    const res = await api().post('/api/portfolio/linkedin/import', { data })
    result.value = JSON.stringify(res.data, null, 2)
    toast.title = 'Imported'
    toast.message = 'Imported and replaced lists.'
    toast.show = true
  } catch {
    toast.title = 'Error'
    toast.message = 'Invalid JSON or server error.'
    toast.show = true
  } finally {
    loading.value = false
  }
}
</script>
