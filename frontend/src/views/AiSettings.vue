<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="flex items-center justify-between">
          <div>
            <div class="text-lg font-semibold">AI Integration Settings</div>
            <div class="text-sm text-muted-foreground">
              Manage Google Gemini AI Configuration.
            </div>
          </div>
          <button
            type="button"
            class="rounded-md p-1 text-muted-foreground transition hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
            title="AI Setup Guide"
            @click="openGuide"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="16" x2="12" y2="12" />
              <line x1="12" y1="8" x2="12.01" y2="8" />
            </svg>
          </button>
        </div>
      </template>

      <div class="space-y-4">
        <div class="rounded-lg border border-border bg-background p-3">
          <div class="text-sm font-medium">Status</div>
          <div
            class="text-xs text-muted-foreground flex items-center gap-2 mt-1"
          >
            <span :class="status.gemini ? 'text-green-600' : 'text-red-500'"
              >●</span
            >
            Gemini AI: {{ status.gemini ? "Configured" : "Missing API Key" }}
          </div>
        </div>

        <div class="space-y-2">
          <div class="text-sm font-medium">Google Gemini API Key</div>
          <Input v-model="apiKey" type="password" placeholder="AIzaSy..." />
          <div class="text-xs text-muted-foreground">
            Get your key from
            <a
              href="https://aistudio.google.com/app/apikey"
              target="_blank"
              class="underline"
              >Google AI Studio</a
            >.
          </div>
        </div>

        <div class="flex gap-2">
          <Button @click="saveKey" :disabled="loading || !apiKey">
            {{ loading ? "Saving..." : "Save API Key" }}
          </Button>
        </div>

        <div
          v-if="message"
          :class="['text-xs', isError ? 'text-red-500' : 'text-green-600']"
        >
          {{ message }}
        </div>
        <div>
          <a
            class="text-primary underline hover:no-underline"
            href="/ai-assistant-guide.html"
            target="_blank"
            rel="noreferrer"
            >Open full guide</a
          >
        </div>
      </div>
    </Card>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import Card from "../components/ui/Card.vue";
import Button from "../components/ui/Button.vue";
import Input from "../components/ui/Input.vue";
import { api } from "../lib/api";

const status = ref({ gemini: false });
const apiKey = ref("");
const loading = ref(false);
const message = ref("");
const isError = ref(false);

async function refresh() {
  try {
    const res = await api().get("/api/ai/config");
    status.value = { gemini: !!res.data?.gemini_configured };
  } catch {}
}

async function saveKey() {
  if (!apiKey.value) return;
  loading.value = true;
  message.value = "";
  isError.value = false;

  try {
    const res = await api().post("/api/ai/config", {
      gemini_api_key: apiKey.value,
    });
    message.value = res.data?.message || "Settings updated";
    apiKey.value = ""; // Clear input for security
    await refresh();
  } catch (e) {
    isError.value = true;
    message.value = e?.response?.data?.message || "Failed to update settings";
  } finally {
    loading.value = false;
  }
}

onMounted(refresh);
</script>
