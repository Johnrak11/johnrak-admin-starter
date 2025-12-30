<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="flex items-start justify-between">
          <div>
            <div class="text-lg font-semibold">Database Backup</div>
            <div class="text-sm text-muted-foreground">
              Configure S3-compatible storage and run backups. When enabled,
              backups run daily at 12:00 AM.
            </div>
          </div>
          <button
            type="button"
            class="rounded-md text-muted-foreground transition hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
            title="How to get AWS S3 info"
            @click="help.open = true"
          >
            ⓘ
          </button>
        </div>
      </template>

      <div class="space-y-4">
        <div class="flex items-center justify-between gap-4">
          <div>
            <div class="text-sm font-medium">Enable auto backup</div>
            <div class="text-xs text-muted-foreground">
              Runs every day at 12:00 AM.
            </div>
          </div>
          <Switch v-model="form.enabled" />
        </div>

        <div class="grid gap-3 md:grid-cols-2">
          <div class="space-y-2">
            <Label>S3 Region</Label>
            <Input v-model="form.s3_region" placeholder="us-east-1" />
          </div>
          <div class="space-y-2">
            <Label>S3 Bucket</Label>
            <Input v-model="form.s3_bucket" placeholder="my-backups" />
          </div>
          <div class="space-y-2">
            <Label>S3 Access Key</Label>
            <Input v-model="form.s3_access_key" placeholder="AKIA..." />
          </div>
          <div class="space-y-2">
            <Label>S3 Secret</Label>
            <Input
              type="password"
              v-model="form.s3_secret"
              placeholder="********"
            />
          </div>
          <div class="space-y-2">
            <Label>S3 Endpoint (optional)</Label>
            <Input
              v-model="form.s3_endpoint"
              placeholder="https://provider-endpoint"
            />
          </div>
          <div class="space-y-2">
            <Label>Path Prefix</Label>
            <Input v-model="form.s3_path_prefix" placeholder="backups" />
          </div>
        </div>

        <div class="flex gap-2">
          <Button @click="save" :disabled="saving">{{
            saving ? "Saving..." : "Save"
          }}</Button>
          <Button variant="ghost" @click="run" :disabled="running">{{
            running ? "Backing up..." : "Backup now"
          }}</Button>
        </div>
        <div v-if="message" class="text-xs text-muted-foreground">
          {{ message }}
        </div>
      </div>
    </Card>

    <!-- help modal -->
    <div
      v-if="help.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 supports-[backdrop-filter]:backdrop-blur-sm"
    >
      <div
        class="w-full max-w-2xl rounded-2xl border border-border bg-card p-4 text-card-foreground shadow-lg"
      >
        <div class="flex items-center justify-between">
          <div class="font-semibold">How to get AWS S3 info</div>
          <button
            type="button"
            class="rounded-md text-muted-foreground transition hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
            @click="help.open = false"
          >
            ✕
          </button>
        </div>

        <div class="mt-3 space-y-3 text-sm">
          <div>
            <div class="font-medium">You need</div>
            <div class="text-muted-foreground">
              s3_region, s3_bucket, s3_access_key, s3_secret
            </div>
          </div>
          <ol class="list-decimal pl-4 space-y-1">
            <li>
              AWS → S3 → Buckets → Create or select your bucket; copy Region and
              Bucket name
            </li>
            <li>AWS → IAM → Users → Create user (programmatic access)</li>
            <li>
              Attach a policy limited to your bucket and prefix (e.g. backups/*)
            </li>
            <li>Create access key; copy Access key ID and Secret access key</li>
            <li>
              Fill the form here and Save, then click “Backup now” to test
            </li>
          </ol>
          <div>
            <a
              class="text-primary underline hover:no-underline"
              href="/aws-s3-setup.html"
              target="_blank"
              rel="noreferrer"
              >Open full guide</a
            >
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { api } from "../lib/api";
import Card from "../components/ui/Card.vue";
import Button from "../components/ui/Button.vue";
import Input from "../components/ui/Input.vue";
import Label from "../components/ui/Label.vue";
import Switch from "../components/ui/Switch.vue";

const form = ref({
  enabled: false,
  s3_region: "",
  s3_bucket: "",
  s3_access_key: "",
  s3_secret: "",
  s3_endpoint: "",
  s3_path_prefix: "backups",
});
const saving = ref(false);
const running = ref(false);
const message = ref("");
const help = ref({ open: false });

onMounted(async () => {
  try {
    const res = await api().get("/api/security/backup/config");
    const cfg = res.data;
    form.value.enabled = !!cfg.enabled;
    form.value.s3_region = cfg.s3?.region || "";
    form.value.s3_bucket = cfg.s3?.bucket || "";
    form.value.s3_endpoint = cfg.s3?.endpoint || "";
    form.value.s3_path_prefix = cfg.s3?.path_prefix || "backups";
  } catch {}
});

async function save() {
  saving.value = true;
  message.value = "";
  try {
    await api().post("/api/security/backup/config", form.value);
    message.value = "Saved";
  } catch (e) {
    message.value = e?.response?.data?.message || "Save failed";
  } finally {
    saving.value = false;
  }
}

async function run() {
  running.value = true;
  message.value = "";
  try {
    const res = await api().post("/api/security/backup/run");
    const uploaded = res.data?.uploaded_key;
    const local = res.data?.local_path;
    message.value = uploaded
      ? "Uploaded: " + uploaded
      : local
      ? "Saved locally: " + local
      : "Done";
  } catch (e) {
    message.value = e?.response?.data?.message || "Backup failed";
  } finally {
    running.value = false;
  }
}
</script>

<style scoped></style>
