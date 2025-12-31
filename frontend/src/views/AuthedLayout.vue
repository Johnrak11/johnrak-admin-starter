<template>
  <div class="min-h-screen bg-background text-foreground">
    <div class="flex">
      <aside
        :class="[
          'sticky top-0 h-screen border-r border-border bg-card p-4',
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
              <path d="M12 8V4H8" /> 
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

      <main class="flex-1 p-6">
        <div class="mb-6">
          <div class="text-2xl font-semibold">{{ pageTitle }}</div>
          <div v-if="settings.showHints" class="text-sm text-muted-foreground">
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
const auth = useAuthStore();
const settings = useSettingsStore();
const router = useRouter();
const route = useRoute();

const navGroups = [
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
    label: "Settings",
    items: [{ to: "/settings", label: "General" }],
  },
  {
    label: "Security",
    items: [
      { to: "/security", label: "Two-Factor & Tokens" },
      { to: "/security/backup", label: "Backup" },
    ],
  },
  {
    label: "AI Integration",
    items: [
      { to: "/ai/settings", label: "Settings" },
    ],
  },
];

const collapsed = reactive({
  Portfolio: false,
  Settings: false,
  Security: false,
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
    Settings: /^\/settings$/,
    Security: /^\/security(\/|$)/,
    "AI Integration": /^\/ai\//,
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
  "/portfolio/profile": "Profile",
  "/portfolio/experiences": "Experiences",
  "/portfolio/educations": "Educations",
  "/portfolio/skills": "Skills",
  "/portfolio/certifications": "Certificates",
  "/portfolio/projects": "Projects",
  "/portfolio/attachments": "CV & Files",
  "/portfolio/linkedin-import": "LinkedIn Import",
  "/settings": "Settings",
  "/security": "Security",
};

const pageTitle = computed(() => titles[route.path] || "Dashboard");

async function onLogout() {
  await auth.logout();
  router.push("/login");
}
</script>
