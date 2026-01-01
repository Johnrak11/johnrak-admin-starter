<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="flex items-center justify-between">
          <div>
            <div class="font-semibold">Projects</div>
            <div class="text-sm text-muted-foreground">
              Add / edit your projects.
            </div>
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

        <div
          v-for="it in items"
          :key="it.id"
          class="rounded-xl border border-border bg-muted/30 p-4"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
              <div class="text-sm font-medium text-foreground">
                {{ headline(it) }}
              </div>
              <div class="mt-1 text-sm text-muted-foreground">
                {{ subtitle(it) }}
              </div>
              <div
                v-if="it.description"
                class="mt-2 whitespace-pre-wrap text-sm text-muted-foreground line-clamp-3"
              >
                {{ it.description }}
              </div>
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
      <div
        class="w-full max-w-2xl rounded-2xl border border-border bg-card p-4 text-card-foreground shadow-lg"
      >
        <div class="flex items-center justify-between">
          <div class="font-semibold">
            {{ modal.mode === "create" ? "Add" : "Edit" }}
          </div>
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
            <Label>Name</Label>
            <Input v-model="draft.name" type="text" placeholder="Gosabai" />
          </div>
          <div class="space-y-2">
            <Label>Slug</Label>
            <Input v-model="draft.slug" type="text" placeholder="gosabai" />
          </div>
          <div class="space-y-2">
            <Label>Tech Stack</Label>
            <Input
              v-model="draft.tech_stack"
              type="text"
              placeholder="Laravel, Vue, Docker"
            />
          </div>
          <div class="space-y-2">
            <Label>Repo URL</Label>
            <Input
              v-model="draft.repo_url"
              type="text"
              placeholder="https://github.com/..."
            />
          </div>
          <div class="space-y-2">
            <Label>Live URL</Label>
            <Input
              v-model="draft.live_url"
              type="text"
              placeholder="https://..."
            />
          </div>
          <div class="space-y-2">
            <Label>Start Date</Label>
            <Input v-model="draft.start_date" type="date" placeholder="" />
          </div>
          <div class="space-y-2">
            <Label>End Date</Label>
            <Input v-model="draft.end_date" type="date" placeholder="" />
          </div>
          <div class="space-y-2">
            <Label>Featured (0/1)</Label>
            <Input v-model="draft.is_featured" type="text" placeholder="0" />
          </div>
          <div class="md:col-span-2 space-y-2">
            <div class="flex items-center justify-between">
              <Label>Description</Label>
              <Button
                variant="ghost"
                size="sm"
                @click="openGenerator"
                class="h-6 text-xs text-blue-600 hover:text-blue-700 hover:bg-blue-50"
              >
                ✨ Generate Case Study
              </Button>
            </div>
            <textarea
              v-model="draft.description"
              class="min-h-[110px] w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
              placeholder="Project summary..."
            />
          </div>
          <div class="space-y-2">
            <Label>Sort order</Label>
            <Input v-model="draft.sort_order" type="number" placeholder="0" />
          </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
          <Button variant="ghost" @click="close">Cancel</Button>
          <Button @click="save" :disabled="saving">{{
            saving ? "Saving..." : "Save"
          }}</Button>
        </div>
      </div>
    </div>

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
        class="w-full max-w-5xl h-[80vh] rounded-2xl border border-border bg-card flex flex-col shadow-2xl"
      >
        <div
          class="flex items-center justify-between border-b border-border p-4"
        >
          <div class="font-semibold flex items-center gap-2">
            <span>✨</span> Auto-Case Study Generator
          </div>
          <button
            type="button"
            class="rounded-md text-muted-foreground transition hover:text-foreground"
            @click="generator.open = false"
          >
            ✕
          </button>
        </div>

        <div class="flex-1 flex overflow-hidden">
          <!-- Left: Input -->
          <div
            class="w-1/3 border-r border-border p-4 flex flex-col gap-4 bg-muted/10"
          >
            <div class="space-y-2">
              <Label>GitHub Repository URL</Label>
              <Input
                v-model="generator.repoUrl"
                placeholder="https://github.com/user/repo"
              />
              <div class="text-xs text-muted-foreground">
                We'll fetch the README.md for context.
              </div>
            </div>

            <div class="space-y-2 flex-1 flex flex-col">
              <Label>Manual Notes</Label>
              <textarea
                v-model="generator.notes"
                class="flex-1 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                placeholder="Dump your raw thoughts here... e.g. 'Used Redis to fix slow query, improved speed by 50%'"
              ></textarea>
            </div>

            <Button
              @click="generate"
              :disabled="generator.loading"
              class="w-full"
            >
              <span v-if="generator.loading" class="mr-2 animate-spin">⏳</span>
              {{ generator.loading ? "Generating..." : "Generate Case Study" }}
            </Button>
          </div>

          <!-- Right: Preview -->
          <div class="w-2/3 p-4 flex flex-col gap-4">
            <div class="flex items-center justify-between">
              <Label>Generated Result (Markdown)</Label>
              <div
                v-if="generator.result"
                class="text-xs text-green-600 font-medium"
              >
                Ready to review
              </div>
            </div>

            <textarea
              v-model="generator.result"
              class="flex-1 w-full rounded-lg border border-input bg-background px-4 py-4 text-sm font-mono leading-relaxed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring resize-none"
              placeholder="Your case study will appear here..."
            ></textarea>

            <div class="flex justify-end gap-2">
              <Button variant="ghost" @click="generator.open = false"
                >Cancel</Button
              >
              <Button @click="applyCaseStudy" :disabled="!generator.result">
                Apply to Description
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
import { normalizeSortOrder } from "./_crudHelpers";

const loading = ref(false);
const saving = ref(false);
const items = ref([]);

const modal = reactive({ open: false, mode: "create", id: null });

const generator = reactive({
  open: false,
  loading: false,
  repoUrl: "",
  notes: "",
  result: "",
});

const draft = reactive({
  name: "",
  slug: "",
  tech_stack: "",
  repo_url: "",
  live_url: "",
  start_date: "",
  end_date: "",
  is_featured: "",
  description: "",
  sort_order: 0,
});

const toast = reactive({ show: false, title: "Saved", message: "" });

function resetDraft() {
  draft.name = "";
  draft.slug = "";
  draft.tech_stack = "";
  draft.repo_url = "";
  draft.live_url = "";
  draft.start_date = "";
  draft.end_date = "";
  draft.is_featured = "";
  draft.description = "";
  draft.sort_order = 0;
}

function openCreate() {
  resetDraft();
  modal.open = true;
  modal.mode = "create";
  modal.id = null;
}

function openEdit(it) {
  resetDraft();
  draft.name = it.name ?? "";
  draft.slug = it.slug ?? "";
  draft.tech_stack = it.tech_stack ?? "";
  draft.repo_url = it.repo_url ?? "";
  draft.live_url = it.live_url ?? "";
  draft.start_date = it.start_date ?? "";
  draft.end_date = it.end_date ?? "";
  draft.is_featured = it.is_featured ?? "";
  draft.description = it.description ?? "";
  draft.sort_order = it.sort_order ?? 0;
  modal.open = true;
  modal.mode = "edit";
  modal.id = it.id;
}

function openGenerator() {
  generator.open = true;
  generator.loading = false;
  generator.repoUrl = draft.repo_url || "";
  generator.notes = draft.description || "";
  generator.result = "";
}

async function generate() {
  if (!generator.repoUrl && !generator.notes) return;
  generator.loading = true;
  try {
    const res = await api().post("/api/ai/case-study", {
      repo_url: generator.repoUrl,
      notes: generator.notes,
    });
    generator.result = res.data?.markdown || "";
  } catch (e) {
    alert(e?.response?.data?.error || "Generation failed");
  } finally {
    generator.loading = false;
  }
}

function applyCaseStudy() {
  draft.description = generator.result;
  generator.open = false;
}

function close() {
  modal.open = false;
}

function headline(it) {
  return it.name || it.title || it.school || it.company || `#${it.id}`;
}

function subtitle(it) {
  const parts = [];
  if (it.company && it.title) parts.push(`${it.title} @ ${it.company}`);
  if (it.school) parts.push(it.school);
  if (it.issuer) parts.push(it.issuer);
  if (it.location) parts.push(it.location);
  return parts.filter(Boolean).join(" · ");
}

async function load() {
  loading.value = true;
  try {
    const res = await api().get("/api/portfolio/projects");
    items.value = res.data.items || [];
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  try {
    const payload = {
      name: draft.name,
      slug: draft.slug,
      tech_stack: draft.tech_stack,
      repo_url: draft.repo_url,
      live_url: draft.live_url,
      start_date: draft.start_date,
      end_date: draft.end_date,
      is_featured: draft.is_featured,
      description: draft.description,
      sort_order: normalizeSortOrder(draft.sort_order),
    };

    if (modal.mode === "create") {
      await api().post("/api/portfolio/projects", payload);
      toast.title = "Created";
      toast.message = "Item created.";
    } else {
      await api().put(`/api/portfolio/projects/${modal.id}`, payload);
      toast.title = "Updated";
      toast.message = "Item updated.";
    }

    toast.show = true;
    close();
    await load();
  } finally {
    saving.value = false;
  }
}

async function remove(it) {
  if (!confirm("Delete this item?")) return;
  await api().delete(`/api/portfolio/projects/${it.id}`);
  toast.title = "Deleted";
  toast.message = "Item deleted.";
  toast.show = true;
  await load();
}

onMounted(load);
</script>
