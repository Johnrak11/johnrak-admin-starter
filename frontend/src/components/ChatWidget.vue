<template>
  <div>
    <div
      v-if="open"
      class="fixed bottom-20 right-4 z-50 w-[380px] rounded-2xl border border-border bg-card p-4 text-card-foreground shadow-2xl flex flex-col max-h-[600px]"
    >
      <div class="flex items-center justify-between border-b border-border pb-2 mb-2">
        <div class="font-semibold flex items-center gap-2">
          <span>✨</span> Johnrak AI Assistant
        </div>
        <button
          class="rounded-md text-muted-foreground hover:text-foreground transition-colors"
          @click="open = false"
        >
          ✕
        </button>
      </div>
      
      <div class="flex-1 overflow-y-auto space-y-4 pr-2 min-h-[300px]" ref="scrollArea">
        <div v-if="msgs.length === 0" class="text-sm text-muted-foreground text-center py-8">
          Ask me about Vorak's projects, skills, or experience!
        </div>
        <div v-for="(m, i) in msgs" :key="i" class="text-sm">
          <div v-if="m.role === 'user'" class="flex justify-end">
             <div class="bg-primary text-primary-foreground px-3 py-2 rounded-lg rounded-tr-none max-w-[85%]">
               {{ m.content }}
             </div>
          </div>
          <div v-else class="flex justify-start">
             <div class="bg-muted text-foreground px-3 py-2 rounded-lg rounded-tl-none max-w-[90%] prose-content" v-html="renderMarkdown(m.content)"></div>
          </div>
        </div>
        <div v-if="loading" class="flex justify-start">
          <div class="bg-muted px-3 py-2 rounded-lg rounded-tl-none text-xs text-muted-foreground animate-pulse">
            Thinking...
          </div>
        </div>
      </div>

      <div class="mt-3 flex gap-2 pt-2 border-t border-border">
        <Input 
          v-model="draft" 
          placeholder="Ask anything..." 
          class="flex-1" 
          @keyup.enter="send"
          :disabled="loading"
        />
        <Button @click="send" :disabled="loading || !draft">Send</Button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, nextTick, watch } from "vue";
import Button from "./ui/Button.vue";
import Input from "./ui/Input.vue";
import { api } from "../lib/api";
import MarkdownIt from 'markdown-it';

const md = new MarkdownIt({
  breaks: true,
  linkify: true
});

const open = ref(false);
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
watch(open, (val) => {
  if (val) scrollToBottom();
});

function toggle() {
  open.value = !open.value;
}

defineExpose({ toggle });

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
  margin-bottom: 0.5rem;
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
}
</style>
