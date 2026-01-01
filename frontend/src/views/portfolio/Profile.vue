<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="flex items-center justify-between">
          <div>
            <div class="font-semibold">Profile</div>
            <div class="text-sm text-muted-foreground">
              This is shown on your public CV website.
            </div>
          </div>
          <Button @click="save" :disabled="loading">{{
            loading ? "Saving..." : "Save"
          }}</Button>
        </div>
      </template>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="space-y-2">
          <Label>Headline</Label>
          <Input
            v-model="form.headline"
            placeholder="Full-stack developer..."
          />
        </div>
        <div class="space-y-2">
          <Label>Location</Label>
          <Input v-model="form.location" placeholder="Phnom Penh, Cambodia" />
        </div>
        <div class="space-y-2">
          <Label>Website</Label>
          <Input
            v-model="form.website_url"
            placeholder="https://johnrak.online"
          />
        </div>
        <div class="space-y-2">
          <Label>GitHub</Label>
          <Input
            v-model="form.github_url"
            placeholder="https://github.com/..."
          />
        </div>
        <div class="space-y-2">
          <Label>LinkedIn</Label>
          <Input
            v-model="form.linkedin_url"
            placeholder="https://linkedin.com/in/..."
          />
        </div>
        <div class="space-y-2">
          <Label>Avatar URL</Label>
          <Input v-model="form.avatar_url" placeholder="https://..." />
        </div>

        <div class="md:col-span-2 space-y-2">
          <div class="flex items-center justify-between">
            <Label>Summary</Label>
            <Button
              variant="ghost"
              size="sm"
              @click="openGenerator"
              class="h-6 text-xs text-blue-600 hover:text-blue-700 hover:bg-blue-50"
            >
              ✨ Generate Bio
            </Button>
          </div>
          <textarea
            v-model="form.summary"
            class="min-h-[100px] w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
            placeholder="Write a short summary..."
          />
        </div>

        <div class="md:col-span-2 space-y-2">
          <Label>About Me</Label>
          <textarea
            v-model="form.about_me"
            class="min-h-[140px] w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
            placeholder="Write a detailed about me section..."
          />
        </div>
      </div>
    </Card>

    <Toast
      :show="toast.show"
      :title="toast.title"
      :message="toast.message"
      @close="toast.show = false"
    />

    <!-- Generator Modal -->
    <div
      v-if="generator.open"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-background/80 p-4 supports-[backdrop-filter]:backdrop-blur-sm"
    >
      <div
        class="w-full max-w-[95vw] md:max-w-4xl h-[90vh] md:h-[80vh] rounded-2xl border border-border bg-card flex flex-col shadow-2xl"
      >
        <div
          class="flex items-center justify-between border-b border-border p-4"
        >
          <div class="font-semibold flex items-center gap-2">
            <span>✨</span> Auto-Bio Generator
          </div>
          <button
            type="button"
            class="rounded-md text-muted-foreground transition hover:text-foreground"
            @click="generator.open = false"
          >
            ✕
          </button>
        </div>

        <div
          class="flex-1 flex flex-col overflow-hidden p-4 md:p-6 gap-4 md:gap-6 overflow-y-auto"
        >
          <div
            class="bg-blue-50/50 p-4 rounded-lg border border-blue-100 text-sm text-blue-800 flex-shrink-0"
          >
            <strong>How it works:</strong> The AI will analyze all your Skills,
            Projects, Experience, and Education to write a cohesive professional
            narrative.
          </div>

          <div
            class="flex-1 flex flex-col md:flex-row gap-4 overflow-visible md:overflow-hidden min-h-0"
          >
            <!-- Summary Preview -->
            <div class="flex-1 flex flex-col gap-2 min-h-[200px] md:min-h-0">
              <Label>Generated Summary (Short)</Label>
              <textarea
                v-model="generator.summary"
                class="flex-1 w-full rounded-lg border border-input bg-background px-4 py-4 text-sm leading-relaxed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring resize-none"
                placeholder="Waiting for generation..."
                readonly
              ></textarea>
            </div>

            <!-- About Me Preview -->
            <div class="flex-[2] flex flex-col gap-2 min-h-[300px] md:min-h-0">
              <Label>Generated About Me (Detailed)</Label>
              <textarea
                v-model="generator.about_me"
                class="flex-1 w-full rounded-lg border border-input bg-background px-4 py-4 text-sm leading-relaxed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring resize-none"
                placeholder="Waiting for generation..."
                readonly
              ></textarea>
            </div>
          </div>

          <div
            class="flex flex-col md:flex-row justify-between items-center pt-2 gap-4 flex-shrink-0"
          >
            <div
              v-if="generator.error"
              class="text-red-500 text-sm text-center md:text-left"
            >
              {{ generator.error }}
            </div>
            <div v-else></div>

            <div class="flex flex-col md:flex-row gap-2 w-full md:w-auto">
              <Button
                variant="ghost"
                @click="generator.open = false"
                class="w-full md:w-auto"
                >Cancel</Button
              >
              <Button
                @click="generate"
                :disabled="generator.loading"
                variant="secondary"
                class="w-full md:w-auto"
              >
                <span v-if="generator.loading" class="mr-2 animate-spin"
                  >⏳</span
                >
                {{ generator.loading ? "Generating..." : "Regenerate" }}
              </Button>
              <Button
                @click="applyGeneration"
                :disabled="!generator.summary && !generator.about_me"
                class="w-full md:w-auto"
              >
                Apply to Profile
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from "vue";
import { api } from "../../lib/api";
import Card from "../../components/ui/Card.vue";
import Input from "../../components/ui/Input.vue";
import Label from "../../components/ui/Label.vue";
import Button from "../../components/ui/Button.vue";
import Toast from "../../components/ui/Toast.vue";

const loading = ref(false);
const form = reactive({
  headline: "",
  summary: "",
  about_me: "",
  location: "",
  website_url: "",
  github_url: "",
  linkedin_url: "",
  avatar_url: "",
});

const generator = reactive({
  open: false,
  loading: false,
  summary: "",
  about_me: "",
  error: "",
});

const toast = reactive({
  show: false,
  title: "Saved",
  message: "Profile updated.",
});

async function load() {
  const res = await api().get("/api/portfolio/profile");
  Object.assign(form, res.data.profile || {});
}

async function save() {
  loading.value = true;
  try {
    await api().put("/api/portfolio/profile", form);
    toast.title = "Saved";
    toast.message = "Profile updated.";
    toast.show = true;
  } finally {
    loading.value = false;
  }
}

function openGenerator() {
  generator.open = true;
  generator.loading = false;
  generator.summary = "";
  generator.about_me = "";
  generator.error = "";
}

async function generate() {
  generator.loading = true;
  generator.error = "";
  try {
    const res = await api().post("/api/ai/bio");
    generator.summary = res.data?.summary || "";
    generator.about_me = res.data?.about_me || "";
  } catch (e) {
    generator.error = e?.response?.data?.error || "Generation failed";
  } finally {
    generator.loading = false;
  }
}

function applyGeneration() {
  if (generator.summary) form.summary = generator.summary;
  if (generator.about_me) form.about_me = generator.about_me;
  generator.open = false;
}

onMounted(load);
</script>
