import { defineStore } from "pinia";
import { api } from "../lib/api";

const KEY = "johnrak_token";
const TRUSTED_KEY = "johnrak_trusted_device";

export const useAuthStore = defineStore("auth", {
  state: () => ({
    token: localStorage.getItem(KEY) || "",
    user: null,
    loading: false,
    error: "",
    twoStep: {
      required: false,
      challenge_id: "",
      challenge_token: "",
    },
  }),
  getters: {
    isAuthed: (s) => !!s.token,
  },
  actions: {
    async login(email, password) {
      this.loading = true;
      this.error = "";
      try {
        const trustedDevice = localStorage.getItem(TRUSTED_KEY) || "";
        const client = api({
          headers: trustedDevice ? { "X-Trusted-Device": trustedDevice } : {},
        });
        const res = await client.post("/api/auth/login", {
          email,
          password,
          device_name: "web",
        });
        if (res.data?.requires_2fa) {
          this.twoStep.required = true;
          this.twoStep.challenge_id = res.data.challenge_id;
          this.twoStep.challenge_token = res.data.challenge_token;
          return "requires_2fa";
        } else {
          this.token = res.data.token;
          localStorage.setItem(KEY, this.token);
          this.user = res.data.user;
          return true;
        }
      } catch (e) {
        const ve = e?.response?.data?.errors;
        this.error =
          ve?.email?.[0] || e?.response?.data?.message || "Login failed";
        return false;
      } finally {
        this.loading = false;
      }
    },
    async login2fa({ code, recovery_code, remember_device }) {
      this.loading = true;
      this.error = "";
      try {
        const res = await api().post("/api/auth/login/2fa", {
          challenge_id: this.twoStep.challenge_id,
          challenge_token: this.twoStep.challenge_token,
          code,
          recovery_code,
          remember_device,
          device_name: "web",
        });
        this.token = res.data.token;
        localStorage.setItem(KEY, this.token);
        this.user = res.data.user;
        if (remember_device && res.data.trusted_device_token) {
          localStorage.setItem(TRUSTED_KEY, res.data.trusted_device_token);
        }
        this.twoStep.required = false;
        this.twoStep.challenge_id = "";
        this.twoStep.challenge_token = "";
        return true;
      } catch (e) {
        this.error = e?.response?.data?.message || "2FA verification failed";
        return false;
      } finally {
        this.loading = false;
      }
    },
    async fetchMe() {
      if (!this.token) return;
      try {
        const res = await api().get("/api/auth/me");
        this.user = res.data.user;
      } catch {
        this.logoutLocal();
      }
    },
    async logout() {
      try {
        await api().post("/api/auth/logout");
      } catch {}
      this.logoutLocal();
    },
    logoutLocal() {
      this.token = "";
      this.user = null;
      localStorage.removeItem(KEY);
      localStorage.removeItem(TRUSTED_KEY);
    },
  },
});
