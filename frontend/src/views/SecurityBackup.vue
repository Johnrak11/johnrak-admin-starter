<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="flex items-center justify-between">
          <div>
            <div class="text-lg font-semibold">Backup Configuration</div>
            <div class="text-sm text-muted-foreground">
              Configure Cloudflare R2 storage for automated database backups.
            </div>
          </div>
          <div class="flex gap-2">
            <span
              v-if="isConfigured"
              class="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full font-medium self-center"
              >Configured</span
            >
            <span
              v-else
              class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs rounded-full font-medium self-center"
              >Not Configured</span
            >
            <span
              v-if="form.enabled"
              class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full font-medium self-center"
              >Active</span
            >
            <span
              v-else
              class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded-full font-medium self-center"
              >Disabled</span
            >
          </div>
        </div>
      </template>

      <div class="space-y-6">
        <!-- Main Toggle -->
        <div class="flex items-center space-x-2">
          <input
            type="checkbox"
            id="enabled"
            v-model="form.enabled"
            class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
          />
          <Label for="enabled" class="font-medium"
            >Enable Automated Backups</Label
          >
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <div class="space-y-2">
            <Label>Provider</Label>
            <select
              v-model="form.provider"
              class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
              <option value="r2">Cloudflare R2</option>
            </select>
          </div>

          <div class="space-y-2">
            <Label>Bucket Name</Label>
            <Input v-model="form.s3_bucket" placeholder="my-backups" />
          </div>

          <div class="space-y-2">
            <Label>Endpoint URL</Label>
            <Input
              v-model="form.s3_endpoint"
              placeholder="https://<ACCOUNT_ID>.r2.cloudflarestorage.com"
            />
            <div class="text-xs text-muted-foreground">
              Required for R2. Found in Cloudflare Dashboard > R2 > Overview.
            </div>
          </div>

          <div class="space-y-2">
            <Label>Region</Label>
            <Input v-model="form.s3_region" placeholder="auto" />
            <div class="text-xs text-muted-foreground">
              Use "auto" for Cloudflare R2.
            </div>
          </div>

          <div class="space-y-2">
            <Label>Access Key ID</Label>
            <Input
              v-model="form.s3_access_key"
              placeholder="Enter Access Key ID"
            />
          </div>

          <div class="space-y-2">
            <Label>Secret Access Key</Label>
            <Input
              v-model="form.s3_secret"
              type="password"
              placeholder="Enter Secret Access Key"
            />
          </div>

          <div class="space-y-2 md:col-span-2">
            <Label>Path Prefix (Optional)</Label>
            <Input
              v-model="form.s3_path_prefix"
              placeholder="backups/johnrak"
            />
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-border">
          <Button variant="ghost" @click="runBackup" :disabled="running">
            {{ running ? "Backing up..." : "Run Manual Backup" }}
          </Button>
          <Button @click="save" :disabled="saving">
            {{ saving ? "Saving..." : "Save Configuration" }}
          </Button>
        </div>
      </div>
    </Card>

    <div
      class="bg-blue-50 border border-blue-100 rounded-lg p-4 text-sm text-blue-800"
    >
      <h4 class="font-semibold mb-2">How to get Cloudflare R2 Credentials:</h4>
      <ol class="list-decimal list-inside space-y-1 ml-1 mb-4">
        <li>Log in to Cloudflare Dashboard and go to <strong>R2</strong>.</li>
        <li>Click <strong>"Manage R2 API Tokens"</strong> (top right).</li>
        <li>Click <strong>"Create API Token"</strong>.</li>
        <li>Select <strong>"Admin Read & Write"</strong> permission.</li>
        <li>
          Copy the <strong>Access Key ID</strong> and
          <strong>Secret Access Key</strong>.
        </li>
        <li>
          Copy the <strong>Endpoint</strong> from the bucket details page
          (remove bucket name from URL if present).
        </li>
      </ol>
      <a href="/aws-s3-setup.html" target="_blank" class="text-blue-700 underline font-medium hover:text-blue-900">
        Open full setup guide &rarr;
      </a>
    </div>

    <Toast
      :show="toast.show"
      :title="toast.title"
      :message="toast.message"
      @close="toast.show = false"
    />
  </div>
</template>

<script setup>
import { onMounted, ref, reactive } from "vue";
import { api } from "../lib/api";
import Card from "../components/ui/Card.vue";
import Button from "../components/ui/Button.vue";
import Input from "../components/ui/Input.vue";
import Label from "../components/ui/Label.vue";
import Toast from "../components/ui/Toast.vue";

const loading = ref(false);
const saving = ref(false);
const running = ref(false);
const toast = reactive({ show: false, title: "", message: "" });

const form = reactive({
  enabled: false,
  provider: "r2",
  s3_bucket: "",
  s3_endpoint: "",
  s3_region: "auto",
  s3_access_key: "",
  s3_secret: "",
  s3_path_prefix: "",
});

async function load() {
  loading.value = true;
  try {
    const res = await api().get("/api/security/backup/config");
    if (res.data) {
      Object.assign(form, res.data);
      if (!form.provider) form.provider = "r2";
      if (!form.s3_region) form.s3_region = "auto";
    }
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  try {
    await api().post("/api/security/backup/config", form);
    toast.title = "Saved";
    toast.message = "Backup configuration updated.";
    toast.show = true;
  } catch (e) {
    toast.title = "Error";
    toast.message = "Failed to save configuration.";
    toast.show = true;
  } finally {
    saving.value = false;
  }
}

async function runBackup() {
  if (!confirm("Run a manual backup now? This may take a few seconds.")) return;
  running.value = true;
  try {
    await api().post("/api/security/backup/run");
    toast.title = "Success";
    toast.message = "Backup initiated successfully.";
    toast.show = true;
  } catch (e) {
    toast.title = "Error";
    toast.message = e.response?.data?.message || "Backup failed.";
    toast.show = true;
  } finally {
    running.value = false;
  }
}

onMounted(load);
</script>
