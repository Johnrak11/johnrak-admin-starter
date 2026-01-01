import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../stores/auth";

import Login from "../views/Login.vue";
import AuthedLayout from "../views/AuthedLayout.vue";
import Dashboard from "../views/Dashboard.vue";
import Settings from "../views/Settings.vue";
import Security from "../views/Security.vue";
import SecurityBackup from "../views/SecurityBackup.vue";
// import AiSearch from "../views/AiSearch.vue";
import AiSettings from "../views/AiSettings.vue";

import Profile from "../views/portfolio/Profile.vue";
import Experiences from "../views/portfolio/Experiences.vue";
import Educations from "../views/portfolio/Educations.vue";
import Skills from "../views/portfolio/Skills.vue";
import Certifications from "../views/portfolio/Certifications.vue";
import Projects from "../views/portfolio/Projects.vue";
import Attachments from "../views/portfolio/Attachments.vue";
import LinkedInImport from "../views/portfolio/LinkedInImport.vue";

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/login", component: Login, meta: { public: true } },
    {
      path: "/",
      component: AuthedLayout,
      children: [
        { path: "", component: Dashboard },
        { path: "/portfolio/profile", component: Profile },
        { path: "/portfolio/experiences", component: Experiences },
        { path: "/portfolio/educations", component: Educations },
        { path: "/portfolio/skills", component: Skills },
        { path: "/portfolio/certifications", component: Certifications },
        { path: "/portfolio/projects", component: Projects },
        { path: "/portfolio/attachments", component: Attachments },
        { path: "/portfolio/linkedin-import", component: LinkedInImport },
        { path: "/settings", component: Settings },
        { path: "/security", component: Security },
        { path: "/security/backup", component: SecurityBackup },
        // { path: "/ai/search", component: AiSearch },
        { path: "/ai/settings", component: AiSettings },
      ],
    },
  ],
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  if (to.meta.public) return true;
  if (!auth.token) return { path: "/login" };
  if (!auth.user) await auth.fetchMe();
  return true;
});

export default router;
