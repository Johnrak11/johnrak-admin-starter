<template>
  <div class="space-y-6">
    <div class="text-lg font-semibold">Database Backup</div>
    <div class="text-sm text-muted-foreground">
      Configure S3-compatible storage and run backups. When enabled, backups run daily at 12:00 AM.
    </div>

    <div class="rounded-lg border border-border bg-background p-4 space-y-4">
      <label class="flex items-center justify-between">
        <div>
          <div class="text-sm font-medium">Enable auto backup</div>
          <div class="text-xs text-muted-foreground">Runs every day at 12:00 AM.</div>
        </div>
        <input type="checkbox" v-model="form.enabled" />
      </label>

      <div class="grid gap-3 md:grid-cols-2">
        <div>
          <div class="text-xs text-muted-foreground">S3 Region</div>
          <input class="w-full" v-model="form.s3_region" placeholder="us-east-1" />
        </div>
        <div>
          <div class="text-xs text-muted-foreground">S3 Bucket</div>
          <input class="w-full" v-model="form.s3_bucket" placeholder="my-backups" />
        </div>
        <div>
          <div class="text-xs text-muted-foreground">S3 Access Key</div>
          <input class="w-full" v-model="form.s3_access_key" placeholder="AKIA..." />
        </div>
        <div>
          <div class="text-xs text-muted-foreground">S3 Secret</div>
          <input class="w-full" type="password" v-model="form.s3_secret" placeholder="********" />
        </div>
        <div>
          <div class="text-xs text-muted-foreground">S3 Endpoint (optional)</div>
          <input class="w-full" v-model="form.s3_endpoint" placeholder="https://<provider-endpoint>" />
        </div>
        <div>
          <div class="text-xs text-muted-foreground">Path Prefix</div>
          <input class="w-full" v-model="form.s3_path_prefix" placeholder="backups" />
        </div>
      </div>

      <div class="flex gap-2">
        <button class="px-3 py-2 rounded border" @click="save" :disabled="saving">Save</button>
        <button class="px-3 py-2 rounded border" @click="run" :disabled="running">Backup now</button>
      </div>
      <div v-if="message" class="text-xs text-muted-foreground">{{ message }}</div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../lib/api'

const form = ref({ enabled: false, s3_region: '', s3_bucket: '', s3_access_key: '', s3_secret: '', s3_endpoint: '', s3_path_prefix: 'backups' })
const saving = ref(false)
const running = ref(false)
const message = ref('')

onMounted(async () => {
  try {
    const res = await api().get('/api/security/backup/config')
    const cfg = res.data
    form.value.enabled = !!cfg.enabled
    form.value.s3_region = cfg.s3?.region || ''
    form.value.s3_bucket = cfg.s3?.bucket || ''
    form.value.s3_endpoint = cfg.s3?.endpoint || ''
    form.value.s3_path_prefix = cfg.s3?.path_prefix || 'backups'
  } catch {}
})

async function save() {
  saving.value = true
  message.value = ''
  try {
    await api().post('/api/security/backup/config', form.value)
    message.value = 'Saved'
  } catch (e) {
    message.value = e?.response?.data?.message || 'Save failed'
  } finally {
    saving.value = false
  }
}

async function run() {
  running.value = true
  message.value = ''
  try {
    const res = await api().post('/api/security/backup/run')
    message.value = 'Uploaded: ' + (res.data?.uploaded_key || '')
  } catch (e) {
    message.value = e?.response?.data?.message || 'Backup failed'
  } finally {
    running.value = false
  }
}
</script>

<style scoped>
input { border: 1px solid var(--border); background: var(--background); color: var(--foreground); padding: 6px; border-radius: 6px; }
</style>

