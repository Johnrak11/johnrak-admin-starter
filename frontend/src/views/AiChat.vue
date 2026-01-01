<template>
  <div
    class="flex flex-col bg-card rounded-xl border border-border shadow-sm overflow-hidden h-[calc(100vh-140px)] md:h-[calc(100vh-120px)]"
  >
    <div class="p-4 border-b border-border bg-muted/20">
      <div class="font-semibold flex items-center gap-2">
        <span class="text-xl">✨</span>
        <div>
          <div>Johnrak AI Assistant</div>
          <div class="text-xs text-muted-foreground font-normal">
            Ask me about Vorak's projects, skills, or experience!
          </div>
        </div>
      </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4 space-y-4" ref="scrollArea">
      <div
        v-if="msgs.length === 0"
        class="flex flex-col items-center justify-center h-full text-center space-y-4 text-muted-foreground p-8"
      >
        <div class="text-4xl">👋</div>
        <p class="max-w-md">
          Hello! I am Vorak's digital representative. I can answer questions
          about his resume, projects, or technical skills.
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 w-full max-w-lg mt-4">
          <button
            @click="quickAsk('Summarize Vorak\'s profile')"
            class="p-3 bg-muted/50 hover:bg-muted rounded-lg text-sm text-left transition-colors border border-border"
          >
            "Summarize Vorak's profile"
          </button>
          <button
            @click="quickAsk('What are his top skills?')"
            class="p-3 bg-muted/50 hover:bg-muted rounded-lg text-sm text-left transition-colors border border-border"
          >
            "What are his top skills?"
          </button>
          <button
            @click="quickAsk('Tell me about the Gosabai project')"
            class="p-3 bg-muted/50 hover:bg-muted rounded-lg text-sm text-left transition-colors border border-border"
          >
            "Tell me about Gosabai"
          </button>
          <button
            @click="quickAsk('Does he know Laravel?')"
            class="p-3 bg-muted/50 hover:bg-muted rounded-lg text-sm text-left transition-colors border border-border"
          >
            "Does he know Laravel?"
          </button>
        </div>
      </div>

      <div v-for="(m, i) in msgs" :key="i" class="text-sm">
        <div v-if="m.role === 'user'" class="flex justify-end">
          <div
            class="bg-primary text-primary-foreground px-4 py-3 rounded-2xl rounded-tr-none max-w-[85%] shadow-sm"
          >
            {{ m.content }}
          </div>
        </div>
        <div v-else class="flex justify-start">
          <div
            class="bg-muted text-foreground px-4 py-3 rounded-2xl rounded-tl-none max-w-[90%] prose-content shadow-sm border border-border/50"
            v-html="renderMarkdown(m.content)"
          ></div>
        </div>
      </div>

      <div v-if="loading" class="flex justify-start">
        <div
          class="bg-muted px-4 py-3 rounded-2xl rounded-tl-none text-sm text-muted-foreground animate-pulse border border-border/50"
        >
          Thinking...
        </div>
      </div>
    </div>

    <div class="p-4 border-t border-border bg-background">
      <div class="flex gap-2 max-w-4xl mx-auto w-full">
        <Input
          v-model="draft"
          placeholder="Ask a question..."
          class="flex-1 h-12"
          @keyup.enter="send"
          :disabled="loading"
        />
        <Button @click="send" :disabled="loading || !draft" class="h-12 px-6">
          <span v-if="loading" class="mr-2 animate-spin">⏳</span>
          Send
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, nextTick, watch, onMounted } from "vue";
import Button from "../components/ui/Button.vue";
import Input from "../components/ui/Input.vue";
import { api } from "../lib/api";
import MarkdownIt from "markdown-it";

const md = new MarkdownIt({
  breaks: true,
  linkify: true,
});

const loading = ref(false);
const draft = ref("");
const msgs = ref([]);
const scrollArea = ref(null);
let conversationId = 0;

function renderMarkdown(text) {
  return md.render(text);
}

function scrollToBottom() {
  nextTick(() => {
    if (scrollArea.value) {
      scrollArea.value.scrollTop = scrollArea.value.scrollHeight;
    }
  });
}

watch(msgs, scrollToBottom, { deep: true });

function quickAsk(text) {
  draft.value = text;
  send();
}

async function send() {
  const text = draft.value.trim();
  if (!text || loading.value) return;

  msgs.value.push({ role: "user", content: text });
  draft.value = "";
  loading.value = true;
  scrollToBottom();

  try {
    const res = await api().post("/api/ai/chat", {
      message: text,
      conversation_id: conversationId,
    });
    conversationId = res.data?.conversation_id || conversationId;
    const reply = res.data?.assistant || "";
    msgs.value.push({ role: "assistant", content: reply });
  } catch (e) {
    msgs.value.push({
      role: "assistant",
      content: "Vorak's AI is resting. Try again in a minute.",
    });
  } finally {
    loading.value = false;
    scrollToBottom();
  }
}

onMounted(() => {
  scrollToBottom();
});
</script>

<style scoped>
:deep(.prose-content ul) {
  list-style-type: disc;
  padding-left: 1.5rem;
  margin-top: 0.5rem;
  margin-bottom: 0.5rem;
}
:deep(.prose-content ol) {
  list-style-type: decimal;
  padding-left: 1.5rem;
  margin-top: 0.5rem;
  margin-bottom: 0.5rem;
}
:deep(.prose-content p) {
  margin-bottom: 0.75rem;
  line-height: 1.6;
}
:deep(.prose-content p:last-child) {
  margin-bottom: 0;
}
:deep(.prose-content strong) {
  font-weight: 700;
}
:deep(.prose-content a) {
  color: hsl(var(--primary));
  text-decoration: underline;
  font-weight: 500;
}
:deep(.prose-content h3) {
  font-weight: 700;
  font-size: 1.1em;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
}
</style>
