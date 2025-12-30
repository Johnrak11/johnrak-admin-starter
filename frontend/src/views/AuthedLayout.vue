<template>
  <div class="min-h-screen bg-background text-foreground">
    <div class="flex">
      <aside
        :class="[
          'sticky top-0 h-screen border-r border-border bg-card p-4',
          settings.compactSidebar ? 'w-60' : 'w-72',
        ]"
      >
        <div>
          <div class="text-lg font-semibold">Johnrak</div>
          <div
            v-if="settings.showHints && !settings.compactSidebar"
            class="text-xs text-muted-foreground"
          >
            personal admin
          </div>
        </div>

        <nav class="mt-6 space-y-1">
          <router-link
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            class="block rounded-lg px-3 py-2 text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground"
            active-class="bg-accent text-accent-foreground"
          >
            {{ item.label }}
          </router-link>
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
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth";
import { useSettingsStore } from "../stores/settings";
import Button from "../components/ui/Button.vue";

const auth = useAuthStore();
const settings = useSettingsStore();
const router = useRouter();
const route = useRoute();

const navItems = [
  { to: "/portfolio/profile", label: "Portfolio · Profile" },
  { to: "/portfolio/experiences", label: "Portfolio · Experiences" },
  { to: "/portfolio/educations", label: "Portfolio · Educations" },
  { to: "/portfolio/skills", label: "Portfolio · Skills" },
  { to: "/portfolio/certifications", label: "Portfolio · Certificates" },
  { to: "/portfolio/projects", label: "Portfolio · Projects" },
  { to: "/portfolio/attachments", label: "Portfolio · CV & Files" },
  { to: "/portfolio/linkedin-import", label: "LinkedIn Import" },
  { to: "/settings", label: "Settings" },
  { to: "/security", label: "Security" },
  { to: "/security/backup", label: "Security · Backup" },
];

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
