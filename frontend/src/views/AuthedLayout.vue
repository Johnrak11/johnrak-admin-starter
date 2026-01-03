<template>
  <div class="min-h-screen bg-background text-foreground">
    <!-- Mobile Header -->
    <header
      class="md:hidden sticky top-0 z-40 w-full border-b border-border bg-card p-4 flex items-center justify-between"
    >
      <div class="flex items-center gap-3">
        <button
          @click="mobileMenuOpen = true"
          class="text-muted-foreground hover:text-foreground"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>
        <span class="font-semibold text-lg">Johnrak</span>
      </div>
      <div class="text-sm font-medium truncate max-w-[150px]">
        {{ pageTitle }}
      </div>
    </header>

    <!-- Mobile Drawer -->
    <div v-if="mobileMenuOpen" class="fixed inset-0 z-50 flex md:hidden">
      <!-- Backdrop -->
      <div
        class="fixed inset-0 bg-background/80 backdrop-blur-sm"
        @click="mobileMenuOpen = false"
      ></div>

      <!-- Sidebar Content -->
      <div
        class="relative flex w-full max-w-xs flex-1 flex-col bg-card p-6 shadow-xl transition-all h-full overflow-y-auto"
      >
        <div class="flex items-center justify-between mb-6">
          <div class="text-xl font-bold">Johnrak</div>
          <button @click="mobileMenuOpen = false" class="text-muted-foreground">
            ✕
          </button>
        </div>

        <nav class="space-y-6 flex-1">
          <div v-for="group in navGroups" :key="group.label" class="space-y-2">
            <div
              class="text-sm font-semibold text-foreground/70 uppercase tracking-wider"
            >
              {{ group.label }}
            </div>
            <div class="space-y-1">
              <router-link
                v-for="item in group.items"
                :key="item.to"
                :to="item.to"
                class="block rounded-lg px-3 py-2 text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                active-class="bg-accent text-accent-foreground font-medium"
                @click="mobileMenuOpen = false"
              >
                {{ item.label }}
              </router-link>
            </div>
          </div>
        </nav>

        <div class="mt-6 border-t border-border pt-4">
          <div class="text-xs text-muted-foreground">Signed in as</div>
          <div class="text-sm font-medium truncate">{{ auth.user?.email }}</div>
          <Button class="mt-3 w-full" variant="ghost" @click="onLogout"
            >Logout</Button
          >
        </div>
      </div>
    </div>

    <div class="flex">
      <!-- Desktop Sidebar -->
      <aside
        :class="[
          'hidden md:block sticky top-0 h-screen border-r border-border bg-card p-4',
          settings.compactSidebar ? 'w-60' : 'w-72',
        ]"
      >
        <div class="flex items-center justify-between">
          <div>
            <div class="text-lg font-semibold">Johnrak</div>
            <div
              v-if="settings.showHints && !settings.compactSidebar"
              class="text-xs text-muted-foreground"
            >
              personal admin
            </div>
          </div>
          <button
            @click="chatWidget?.toggle()"
            class="text-muted-foreground hover:text-foreground hover:scale-110 transition-all p-2 rounded-md hover:bg-muted"
            title="AI Chat"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="h-7 w-7 sm:h-6 sm:w-6 animate-pulse"
            >
              4" />
              <rect width="16" height="12" x="4" y="8" rx="2" />
              <path d="M2 14h2" />
              <path d="M20 14h2" />
              <path d="M15 13v2" />
              <path d="M9 13v2" />
            </svg>
          </button>
        </div>

        <nav class="mt-6 space-y-4">
          <div v-for="group in navGroups" :key="group.label" class="space-y-1">
            <button
              type="button"
              class="flex w-full items-center justify-between px-3 py-1 text-xs text-muted-foreground hover:text-foreground"
              @click="toggle(group.label)"
            >
              <span>{{ group.label }}</span>
              <span class="text-muted-foreground">{{
                collapsed[group.label] ? "▸" : "▾"
              }}</span>
            </button>
            <div v-if="!collapsed[group.label]" class="space-y-1">
              <router-link
                v-for="item in group.items"
                :key="item.to"
                :to="item.to"
                class="block rounded-lg px-3 py-2 text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                active-class="bg-accent text-accent-foreground"
              >
                {{ item.label }}
              </router-link>
            </div>
          </div>
        </nav>

        <div class="mt-6 border-t border-border pt-4">
          <div class="text-xs text-muted-foreground">Signed in as</div>
          <div class="text-sm">{{ auth.user?.email }}</div>
          <Button class="mt-3 w-full" variant="ghost" @click="onLogout"
            >Logout</Button
          >
        </div>
      </aside>

      <main class="flex-1 p-4 md:p-6 overflow-x-hidden min-w-0">
        <div class="mb-6">
          <div class="text-2xl font-semibold truncate">{{ pageTitle }}</div>
          <div
            v-if="settings.showHints"
            class="text-sm text-muted-foreground truncate"
          >
            Secure admin dashboard
          </div>
        </div>

        <router-view />
        <ChatWidget ref="chatWidget" />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, watch, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth";
import { useSettingsStore } from "../stores/settings";
import Button from "../components/ui/Button.vue";
import ChatWidget from "../components/ChatWidget.vue";

const chatWidget = ref(null);
const mobileMenuOpen = ref(false);
const auth = useAuthStore();
const settings = useSettingsStore();
const router = useRouter();
const route = useRoute();

const navGroups = [
  {
    label: "Main",
    items: [{ to: "/", label: "Dashboard" }],
  },
  {
    label: "Portfolio",
    items: [
      { to: "/portfolio/profile", label: "Profile" },
      { to: "/portfolio/experiences", label: "Experiences" },
      { to: "/portfolio/educations", label: "Educations" },
      { to: "/portfolio/skills", label: "Skills" },
      { to: "/portfolio/certifications", label: "Certificates" },
      { to: "/portfolio/projects", label: "Projects" },
      { to: "/portfolio/attachments", label: "CV & Files" },
      { to: "/portfolio/linkedin-import", label: "LinkedIn Import" },
    ],
  },
  {
    label: "Intelligence",
    items: [
      { to: "/crypto", label: "TradeMind (Crypto)" },
      { to: "/ai/chat", label: "Chat with AI" },
    ],
  },
  {
    label: "System",
    items: [
      { to: "/settings", label: "General Settings" },
      { to: "/ai/settings", label: "Gemini Configuration" },
      { to: "/security", label: "Security & Tokens" },
      { to: "/security/backup", label: "Backups" },
    ],
  },
];
const collapsed = reactive({
  Main: false,
  Portfolio: false,
  Intelligence: false,
  System: false,
});
function toggle(label) {
  const next = !collapsed[label];
  if (settings.accordionSidebar && next) {
    Object.keys(collapsed).forEach((k) => (collapsed[k] = true));
    collapsed[label] = false;
  } else {
    collapsed[label] = !collapsed[label];
  }
}

function ensureActiveGroupOpen() {
  if (!settings.accordionSidebar) return;
  const path = route.path;
  const map = {
    Portfolio: /^\/portfolio\//,
    System: /^\/(settings|security|ai\/settings)/,
  };
  Object.keys(collapsed).forEach((k) => (collapsed[k] = true));
  for (const [label, re] of Object.entries(map)) {
    if (re.test(path)) {
      collapsed[label] = false;
      break;
    }
  }
}

onMounted(() => ensureActiveGroupOpen());
watch(() => [route.path, settings.accordionSidebar], ensureActiveGroupOpen);

const titles = {
  "/": "Unified Modular Dashboard",
  "/portfolio/profile": "Profile",
  "/portfolio/experiences": "Experiences",
  "/portfolio/educations": "Educations",
  "/portfolio/skills": "Skills",
  "/portfolio/certifications": "Certificates",
  "/portfolio/projects": "Projects",
  "/portfolio/attachments": "CV & Files",
  "/portfolio/linkedin-import": "LinkedIn Import",
  "/crypto": "TradeMind AI",
  "/ai/chat": "Chat with AI",
  "/settings": "Settings",
  "/security": "Security",
};

const pageTitle = computed(() => titles[route.path] || "Dashboard");

async function onLogout() {
  await auth.logout();
  router.push("/login");
}
</script>
