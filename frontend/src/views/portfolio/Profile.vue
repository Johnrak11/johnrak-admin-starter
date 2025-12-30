<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="flex items-center justify-between">
          <div>
            <div class="font-semibold">Profile</div>
            <div class="text-sm text-muted-foreground">This is shown on your public CV website.</div>
          </div>
          <Button @click="save" :disabled="loading">{{ loading ? 'Saving...' : 'Save' }}</Button>
        </div>
      </template>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="space-y-2">
          <Label>Headline</Label>
          <Input v-model="form.headline" placeholder="Full-stack developer..." />
        </div>
        <div class="space-y-2">
          <Label>Location</Label>
          <Input v-model="form.location" placeholder="Phnom Penh, Cambodia" />
        </div>
        <div class="space-y-2">
          <Label>Website</Label>
          <Input v-model="form.website_url" placeholder="https://johnrak.online" />
        </div>
        <div class="space-y-2">
          <Label>GitHub</Label>
          <Input v-model="form.github_url" placeholder="https://github.com/..." />
        </div>
        <div class="space-y-2">
          <Label>LinkedIn</Label>
          <Input v-model="form.linkedin_url" placeholder="https://linkedin.com/in/..." />
        </div>
        <div class="space-y-2">
          <Label>Avatar URL</Label>
          <Input v-model="form.avatar_url" placeholder="https://..." />
        </div>

        <div class="md:col-span-2 space-y-2">
          <Label>Summary</Label>
          <textarea
            v-model="form.summary"
            class="min-h-[140px] w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
            placeholder="Write a short bio..."
          />
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
const form = reactive({
  headline: '',
  summary: '',
  location: '',
  website_url: '',
  github_url: '',
  linkedin_url: '',
  avatar_url: ''
})

const toast = reactive({ show: false, title: 'Saved', message: 'Profile updated.' })

async function load() {
  const res = await api().get('/api/portfolio/profile')
  Object.assign(form, res.data.profile || {})
}

async function save() {
  loading.value = true
  try {
    await api().put('/api/portfolio/profile', form)
    toast.title = 'Saved'
    toast.message = 'Profile updated.'
    toast.show = true
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
