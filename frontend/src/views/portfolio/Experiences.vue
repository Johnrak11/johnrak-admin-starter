<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="flex items-center justify-between">
          <div>
            <div class="font-semibold">Experiences</div>
            <div class="text-sm text-muted-foreground">
              Add / edit your experiences.
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
          <div
            class="flex flex-col sm:flex-row sm:items-center justify-between gap-4"
          >
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium text-foreground truncate">
                {{ headline(it) }}
              </div>
              <div class="mt-1 text-sm text-muted-foreground truncate">
                {{ subtitle(it) }}
              </div>
              <div
                v-if="it.description"
                class="mt-2 whitespace-pre-wrap text-sm text-muted-foreground line-clamp-3"
              >
                {{ it.description }}
              </div>
            </div>
            <div class="flex gap-2 self-end sm:self-auto">
              <Button variant="ghost" size="sm" @click="openEdit(it)"
                >Edit</Button
              >
              <Button variant="danger" size="sm" @click="remove(it)"
                >Delete</Button
              >
            </div>
          </div>
        </div>
      </div>
    </Card>

    <!-- modal -->
    <div
      v-if="modal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 supports-[backdrop-filter]:backdrop-blur-sm overflow-y-auto"
    >
      <div
        class="w-full max-w-[95vw] md:max-w-2xl rounded-2xl border border-border bg-card p-4 text-card-foreground shadow-lg my-8 md:my-0"
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
            <Label>Company</Label>
            <Input v-model="draft.company" type="text" placeholder="Google" />
          </div>
          <div class="space-y-2">
            <Label>Title</Label>
            <Input
              v-model="draft.title"
              type="text"
              placeholder="Software Engineer"
            />
          </div>
          <div class="space-y-2">
            <Label>Location</Label>
            <Input
              v-model="draft.location"
              type="text"
              placeholder="Phnom Penh"
            />
          </div>
          <div class="space-y-2">
            <Label>Employment Type</Label>
            <Input
              v-model="draft.employment_type"
              type="text"
              placeholder="Full-time"
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
            <Label>Is Current (0/1)</Label>
            <Input v-model="draft.is_current" type="text" placeholder="0" />
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
                ✨ Generate Description
              </Button>
            </div>
            <textarea
              v-model="draft.description"
              class="min-h-[110px] w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
              placeholder="What you did..."
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
        class="w-full max-w-[95vw] md:max-w-5xl h-[90vh] md:h-[80vh] rounded-2xl border border-border bg-card flex flex-col shadow-2xl"
      >
        <div
          class="flex items-center justify-between border-b border-border p-4"
        >
          <div class="font-semibold flex items-center gap-2">
            <span>✨</span> Experience Description Generator
          </div>
          <button
            type="button"
            class="rounded-md text-muted-foreground transition hover:text-foreground"
            @click="generator.open = false"
          >
            ✕
          </button>
        </div>

        <div class="flex-1 flex flex-col md:flex-row overflow-hidden">
          <!-- Left: Input -->
          <div
            class="w-full md:w-1/3 border-b md:border-b-0 md:border-r border-border p-4 flex flex-col gap-4 bg-muted/10 h-1/2 md:h-full overflow-y-auto"
          >
            <div class="space-y-2 flex-1 flex flex-col">
              <Label>Manual Notes</Label>
              <textarea
                v-model="generator.notes"
                class="flex-1 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                placeholder="List your key responsibilities, achievements, and technologies used..."
              ></textarea>
            </div>

            <Button
              @click="generate"
              :disabled="generator.loading"
              class="w-full"
            >
              <span v-if="generator.loading" class="mr-2 animate-spin">⏳</span>
              {{ generator.loading ? "Generating..." : "Generate Description" }}
            </Button>
          </div>

          <!-- Right: Preview -->
          <div
            class="w-full md:w-2/3 p-4 flex flex-col gap-4 h-1/2 md:h-full overflow-y-auto"
          >
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
              placeholder="Your description will appear here..."
            ></textarea>

            <div class="flex justify-end gap-2">
              <Button variant="ghost" @click="generator.open = false"
                >Cancel</Button
              >
              <Button @click="applyGeneration" :disabled="!generator.result">
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

const draft = reactive({
  company: "",
  title: "",
  location: "",
  employment_type: "",
  start_date: "",
  end_date: "",
  is_current: "",
  description: "",
  sort_order: 0,
});

const generator = reactive({
  open: false,
  loading: false,
  notes: "",
  result: "",
});

const toast = reactive({ show: false, title: "Saved", message: "" });

function resetDraft() {
  draft.company = "";
  draft.title = "";
  draft.location = "";
  draft.employment_type = "";
  draft.start_date = "";
  draft.end_date = "";
  draft.is_current = "";
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
  draft.company = it.company ?? "";
  draft.title = it.title ?? "";
  draft.location = it.location ?? "";
  draft.employment_type = it.employment_type ?? "";
  draft.start_date = it.start_date ?? "";
  draft.end_date = it.end_date ?? "";
  draft.is_current = it.is_current ?? "";
  draft.description = it.description ?? "";
  draft.sort_order = it.sort_order ?? 0;
  modal.open = true;
  modal.mode = "edit";
  modal.id = it.id;
}

function openGenerator() {
  generator.open = true;
  generator.loading = false;
  generator.notes = draft.description || "";
  generator.result = "";
}

async function generate() {
  if (!generator.notes) return;
  generator.loading = true;
  try {
    const res = await api().post("/api/ai/case-study", {
      notes: generator.notes,
      type: "experience",
    });
    generator.result = res.data?.markdown || "";
  } catch (e) {
    alert(e?.response?.data?.error || "Generation failed");
  } finally {
    generator.loading = false;
  }
}

function applyGeneration() {
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
    const res = await api().get("/api/portfolio/experiences");
    items.value = res.data.items || [];
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  try {
    const payload = {
      company: draft.company,
      title: draft.title,
      location: draft.location,
      employment_type: draft.employment_type,
      start_date: draft.start_date,
      end_date: draft.end_date,
      is_current: draft.is_current || 0,
      description: draft.description,
      sort_order: normalizeSortOrder(draft.sort_order),
    };

    if (modal.mode === "create") {
      await api().post("/api/portfolio/experiences", payload);
      toast.title = "Created";
      toast.message = "Item created.";
    } else {
      await api().put(`/api/portfolio/experiences/${modal.id}`, payload);
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
  await api().delete(`/api/portfolio/experiences/${it.id}`);
  toast.title = "Deleted";
  toast.message = "Item deleted.";
  toast.show = true;
  await load();
}

onMounted(load);
</script>
