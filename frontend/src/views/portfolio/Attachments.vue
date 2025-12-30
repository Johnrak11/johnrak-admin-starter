<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div>
          <div class="font-semibold">CV & Files</div>
          <div class="text-sm text-muted-foreground">Upload CV and certificates (private storage).</div>
        </div>
      </template>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="space-y-2">
          <Label>Category</Label>
          <select
            v-model="category"
            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
          >
            <option value="cv">CV</option>
            <option value="certificate">Certificate</option>
            <option value="other">Other</option>
          </select>
        </div>

        <div class="space-y-2 md:col-span-2">
          <Label>Title (optional)</Label>
          <Input v-model="title" placeholder="My CV 2026" />
        </div>

        <div class="md:col-span-3 space-y-2">
          <Label>File</Label>
          <input
            type="file"
            @change="onFile"
            class="block w-full text-sm text-muted-foreground file:mr-4 file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary-foreground hover:file:bg-primary/90"
          />
        </div>

        <div class="md:col-span-3">
          <Button @click="upload" :disabled="uploading || !file">
            {{ uploading ? 'Uploading...' : 'Upload' }}
          </Button>
        </div>
      </div>
    </Card>

    <Card>
      <template #header>
        <div class="flex items-center justify-between">
          <div class="font-semibold">Files</div>
          <Button variant="ghost" @click="load">Refresh</Button>
        </div>
      </template>

      <div v-if="loading" class="text-sm text-muted-foreground">Loading...</div>

      <div v-else class="space-y-3">
        <div v-if="items.length === 0" class="text-sm text-muted-foreground">No uploads yet.</div>

        <div v-for="it in items" :key="it.id" class="flex items-center justify-between rounded-xl border border-border bg-muted/30 p-4">
          <div>
            <div class="text-sm font-medium">{{ it.title || it.original_name }}</div>
            <div class="mt-1 text-xs text-muted-foreground">
              {{ it.category }} · {{ formatBytes(it.size_bytes) }}
            </div>
          </div>
          <div class="flex gap-2">
            <Button variant="ghost" @click="download(it)">Download</Button>
            <Button variant="danger" @click="remove(it)">Delete</Button>
          </div>
        </div>
      </div>
    </Card>

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

const loading = ref(false)
const uploading = ref(false)
const items = ref([])

const category = ref('cv')
const title = ref('')
const file = ref(null)

const toast = reactive({ show: false, title: 'Done', message: '' })

function onFile(e) {
  file.value = e.target.files?.[0] || null
}

function formatBytes(bytes) {
  const b = Number(bytes || 0)
  if (b < 1024) return `${b} B`
  const kb = b / 1024
  if (kb < 1024) return `${kb.toFixed(1)} KB`
  const mb = kb / 1024
  return `${mb.toFixed(1)} MB`
}

async function load() {
  loading.value = true
  try {
    const res = await api().get('/api/portfolio/attachments')
    items.value = res.data.items || []
  } finally {
    loading.value = false
  }
}

async function upload() {
  if (!file.value) return
  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('category', category.value)
    if (title.value) fd.append('title', title.value)
    fd.append('file', file.value)

    await api().post('/api/portfolio/attachments', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    toast.title = 'Uploaded'
    toast.message = 'File uploaded.'
    toast.show = true

    title.value = ''
    file.value = null
    await load()
  } finally {
    uploading.value = false
  }
}

async function download(it) {
  const res = await api().get(`/api/portfolio/attachments/${it.id}/download`, { responseType: 'blob' })
  const blob = new Blob([res.data])
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = it.original_name || 'file'
  a.click()
  URL.revokeObjectURL(url)
}

async function remove(it) {
  if (!confirm('Delete this file?')) return
  await api().delete(`/api/portfolio/attachments/${it.id}`)
  toast.title = 'Deleted'
  toast.message = 'File deleted.'
  toast.show = true
  await load()
}

onMounted(load)
</script>
