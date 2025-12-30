<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="flex items-center justify-between">
          <div>
            <div class="font-semibold">Educations</div>
            <div class="text-sm text-muted-foreground">Add / edit your educations.</div>
          </div>
          <Button @click="openCreate">Add</Button>
        </div>
      </template>

      <div v-if="loading" class="text-sm text-muted-foreground">Loading...</div>

      <div v-else class="space-y-3">
        <div
          v-if="items.length === 0"
          class="rounded-xl border border-border bg-muted/40 p-4 text-sm text-muted-foreground"
        >
          No items yet.
        </div>

        <div v-for="it in items" :key="it.id" class="rounded-xl border border-border bg-muted/30 p-4">
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
              <div class="text-sm font-medium text-foreground">{{ headline(it) }}</div>
              <div class="mt-1 text-sm text-muted-foreground">{{ subtitle(it) }}</div>
              <div v-if="it.description" class="mt-2 whitespace-pre-wrap text-sm text-muted-foreground">{{ it.description }}</div>
            </div>
            <div class="flex gap-2">
              <Button variant="ghost" @click="openEdit(it)">Edit</Button>
              <Button variant="danger" @click="remove(it)">Delete</Button>
            </div>
          </div>
        </div>
      </div>
    </Card>

    <!-- modal -->
    <div
      v-if="modal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 supports-[backdrop-filter]:backdrop-blur-sm"
    >
      <div class="w-full max-w-2xl rounded-2xl border border-border bg-card p-4 text-card-foreground shadow-lg">
        <div class="flex items-center justify-between">
          <div class="font-semibold">{{ modal.mode === 'create' ? 'Add' : 'Edit' }}</div>
          <button
            type="button"
            class="rounded-md text-muted-foreground transition hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
            @click="close"
          >
            ✕
          </button>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <div class="space-y-2">
              <Label>School</Label>
              <Input v-model="draft.school" type="text" placeholder="University" />
            </div>
          <div class="space-y-2">
              <Label>Degree</Label>
              <Input v-model="draft.degree" type="text" placeholder="Bachelor" />
            </div>
          <div class="space-y-2">
              <Label>Field of Study</Label>
              <Input v-model="draft.field_of_study" type="text" placeholder="Computer Science" />
            </div>
          <div class="space-y-2">
              <Label>Start Date</Label>
              <Input v-model="draft.start_date" type="date" placeholder="" />
            </div>
          <div class="space-y-2">
              <Label>End Date</Label>
              <Input v-model="draft.end_date" type="date" placeholder="" />
            </div>
          <div class="md:col-span-2 space-y-2">
              <Label>Description</Label>
              <textarea
                v-model="draft.description"
                class="min-h-[110px] w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                placeholder="Highlights..."
              />
            </div>
          <div class="space-y-2">
            <Label>Sort order</Label>
            <Input v-model="draft.sort_order" type="number" placeholder="0" />
          </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
          <Button variant="ghost" @click="close">Cancel</Button>
          <Button @click="save" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</Button>
        </div>
      </div>
    </div>

    <Toast :show="toast.show" :title="toast.title" :message="toast.message" @close="toast.show=false" />
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { api } from '../../lib/api'
import Card from '../../components/ui/Card.vue'
import Input from '../../components/ui/Input.vue'
import Label from '../../components/ui/Label.vue'
import Button from '../../components/ui/Button.vue'
import Toast from '../../components/ui/Toast.vue'
import { normalizeSortOrder } from './_crudHelpers'

const loading = ref(false)
const saving = ref(false)
const items = ref([])

const modal = reactive({ open: false, mode: 'create', id: null })

const draft = reactive({
  school: '',
  degree: '',
  field_of_study: '',
  start_date: '',
  end_date: '',
  description: '',
  sort_order: 0
})

const toast = reactive({ show: false, title: 'Saved', message: '' })

function resetDraft() {
  draft.school = ''
  draft.degree = ''
  draft.field_of_study = ''
  draft.start_date = ''
  draft.end_date = ''
  draft.description = ''
  draft.sort_order = 0
}

function openCreate() {
  resetDraft()
  modal.open = true
  modal.mode = 'create'
  modal.id = null
}

function openEdit(it) {
  resetDraft()
  draft.school = it.school ?? ''
  draft.degree = it.degree ?? ''
  draft.field_of_study = it.field_of_study ?? ''
  draft.start_date = it.start_date ?? ''
  draft.end_date = it.end_date ?? ''
  draft.description = it.description ?? ''
  draft.sort_order = it.sort_order ?? 0
  modal.open = true
  modal.mode = 'edit'
  modal.id = it.id
}

function close() {
  modal.open = false
}

function headline(it) {
  return it.name || it.title || it.school || it.company || `#${it.id}`
}

function subtitle(it) {
  const parts = []
  if (it.company && it.title) parts.push(`${it.title} @ ${it.company}`)
  if (it.school) parts.push(it.school)
  if (it.issuer) parts.push(it.issuer)
  if (it.location) parts.push(it.location)
  return parts.filter(Boolean).join(' · ')
}

async function load() {
  loading.value = true
  try {
    const res = await api().get('/api/portfolio/educations')
    items.value = res.data.items || []
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    const payload = {
      school: draft.school,
      degree: draft.degree,
      field_of_study: draft.field_of_study,
      start_date: draft.start_date,
      end_date: draft.end_date,
      description: draft.description,
      sort_order: normalizeSortOrder(draft.sort_order)
    }

    if (modal.mode === 'create') {
      await api().post('/api/portfolio/educations', payload)
      toast.title = 'Created'
      toast.message = 'Item created.'
    } else {
      await api().put(`/api/portfolio/educations/${modal.id}`, payload)
      toast.title = 'Updated'
      toast.message = 'Item updated.'
    }

    toast.show = true
    close()
    await load()
  } finally {
    saving.value = false
  }
}

async function remove(it) {
  if (!confirm('Delete this item?')) return
  await api().delete(`/api/portfolio/educations/${it.id}`)
  toast.title = 'Deleted'
  toast.message = 'Item deleted.'
  toast.show = true
  await load()
}

onMounted(load)
</script>
