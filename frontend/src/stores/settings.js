import { defineStore } from "pinia";

const KEY = "johnrak_settings";

function readSettings() {
  try {
    const raw = localStorage.getItem(KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== "object") return null;
    return parsed;
  } catch {
    return null;
  }
}

export const useSettingsStore = defineStore("settings", {
  state: () => ({
    mode: "dark",
    accent: "slate",
    compactSidebar: false,
    showHints: true,
  }),
  actions: {
    init() {
      const saved = readSettings();
      if (saved?.mode === "light" || saved?.mode === "dark")
        this.mode = saved.mode;
      if (typeof saved?.accent === "string" && saved.accent)
        this.accent = saved.accent;
      if (typeof saved?.compactSidebar === "boolean")
        this.compactSidebar = saved.compactSidebar;
      if (typeof saved?.showHints === "boolean")
        this.showHints = saved.showHints;
    },
    persist() {
      localStorage.setItem(
        KEY,
        JSON.stringify({
          mode: this.mode,
          accent: this.accent,
          compactSidebar: this.compactSidebar,
          showHints: this.showHints,
        })
      );
    },
    setMode(mode) {
      this.mode = mode;
      this.persist();
    },
    toggleMode() {
      this.setMode(this.mode === "dark" ? "light" : "dark");
    },
    setAccent(accent) {
      this.accent = accent;
      this.persist();
    },
    setCompactSidebar(value) {
      this.compactSidebar = value;
      this.persist();
    },
    setShowHints(value) {
      this.showHints = value;
      this.persist();
    },
    reset() {
      this.mode = "dark";
      this.accent = "slate";
      this.compactSidebar = false;
      this.showHints = true;
      this.persist();
    },
  },
});
