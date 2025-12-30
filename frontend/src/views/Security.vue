<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="text-lg font-semibold">Two-Factor Authentication</div>
        <div class="text-sm text-muted-foreground">
          Protect your account with a 6-digit code
        </div>
      </template>

      <div class="space-y-4">
        <div class="rounded-lg border border-border bg-background p-3">
          <div class="text-sm">
            Status: <span class="font-medium">{{ statusText }}</span>
          </div>
          <div class="text-xs text-muted-foreground">
            Confirmed at: {{ status.confirmed_at || "—" }}
          </div>
        </div>

        <div class="flex gap-2">
          <Button v-if="!enabled" @click="setup" :disabled="loading">{{
            loading ? "Preparing..." : "Enable 2FA"
          }}</Button>
          <Button
            v-else
            variant="danger"
            @click="disable"
            :disabled="loading"
            >{{ loading ? "Disabling..." : "Disable 2FA" }}</Button
          >
          <Button
            v-if="enabled"
            variant="ghost"
            @click="regen"
            :disabled="loading"
            >{{
              loading ? "Regenerating..." : "Regenerate recovery codes"
            }}</Button
          >
        </div>
      </div>
    </Card>

    <Card>
      <template #header>
        <div class="text-lg font-semibold">Portfolio Sync Token</div>
        <div class="text-sm text-muted-foreground">
          Generate a 24h token to sync your portfolio site
        </div>
      </template>
      <div class="space-y-4">
        <div class="flex gap-2">
          <Button @click="genToken" :disabled="tokenLoading">{{
            tokenLoading ? "Generating..." : "Generate Sync Token"
          }}</Button>
          <Button v-if="syncToken" variant="ghost" @click="copyToken"
            >Copy</Button
          >
        </div>
        <div
          v-if="syncToken"
          class="rounded-lg border border-border bg-background p-3 text-sm"
        >
          <div>
            <span class="text-muted-foreground">Token:</span>
            <span class="font-mono break-all">{{ syncToken }}</span>
          </div>
          <div class="text-xs text-muted-foreground mt-1">
            Expires at: {{ tokenExpires || "—" }}
          </div>
        </div>
        <div v-else class="text-sm text-muted-foreground">No token yet.</div>
      </div>
    </Card>

    <Card v-if="qrSvg || qrPng">
      <template #header>
        <div class="text-lg font-semibold">Scan QR & Confirm</div>
        <div class="text-sm text-muted-foreground">
          Scan with Google/Microsoft Authenticator and enter code
        </div>
      </template>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="flex items-center justify-center p-4">
          <img
            v-if="qrPng"
            :src="qrPng"
            class="rounded-lg border border-border bg-background p-4"
            alt="QR"
          />
          <div
            v-else
            v-html="qrSvg"
            class="rounded-lg border border-border bg-background p-4"
          ></div>
        </div>
        <div class="space-y-3">
          <div class="space-y-2">
            <Label>6-digit code</Label>
            <Input v-model="confirmCode" placeholder="123456" />
          </div>
          <Button @click="confirm" :disabled="loading">{{
            loading ? "Confirming..." : "Confirm"
          }}</Button>
        </div>
      </div>
    </Card>

    <Card v-if="recovery.length">
      <template #header>
        <div class="text-lg font-semibold">Recovery Codes</div>
        <div class="text-sm text-muted-foreground">
          Store safely. Shown once.
        </div>
      </template>
      <div class="grid grid-cols-2 gap-2 md:grid-cols-3">
        <div
          v-for="c in recovery"
          :key="c"
          class="rounded-lg border border-border bg-background px-3 py-2 font-mono text-sm"
        >
          {{ c }}
        </div>
      </div>
    </Card>
  </div>
</template>

<script setup>
import { onMounted, ref, computed } from "vue";
import Card from "../components/ui/Card.vue";
import Button from "../components/ui/Button.vue";
import Input from "../components/ui/Input.vue";
import Label from "../components/ui/Label.vue";
import { api } from "../lib/api";

const loading = ref(false);
const status = ref({ enabled: false, confirmed_at: null });
const qrSvg = ref("");
const qrPng = ref("");
const confirmCode = ref("");
const recovery = ref([]);
const syncToken = ref("");
const tokenExpires = ref("");
const tokenLoading = ref(false);

const enabled = computed(() => !!status.value.enabled);
const statusText = computed(() => (enabled.value ? "Enabled" : "Disabled"));

async function loadStatus() {
  const res = await api().get("/api/security/2fa/status");
  status.value = res.data;
}

async function setup() {
  loading.value = true;
  try {
    const res = await api().post("/api/security/2fa/setup");
    qrSvg.value = res.data.qr_svg || "";
    qrPng.value = res.data.qr_png_base64 || "";
  } finally {
    loading.value = false;
  }
}

async function genToken() {
  tokenLoading.value = true;
  try {
    const res = await api().post("/api/security/portfolio-sync/token");
    syncToken.value = res.data.token || "";
    tokenExpires.value = res.data.expires_at || "";
  } catch (e) {
    syncToken.value = "";
    tokenExpires.value = "";
  } finally {
    tokenLoading.value = false;
  }
}

function copyToken() {
  if (!syncToken.value) return;
  navigator.clipboard.writeText(syncToken.value).catch(() => {});
}

async function confirm() {
  loading.value = true;
  try {
    const res = await api().post("/api/security/2fa/confirm", {
      code: confirmCode.value,
    });
    recovery.value = res.data.recovery_codes || [];
    qrSvg.value = "";
    qrPng.value = "";
    await loadStatus();
  } finally {
    loading.value = false;
  }
}

async function disable() {
  const code = prompt("Enter 2FA code or leave blank to use recovery code");
  const recovery_code = !code ? prompt("Enter recovery code") : "";
  loading.value = true;
  try {
    await api().post("/api/security/2fa/disable", { code, recovery_code });
    recovery.value = [];
    qrSvg.value = "";
    qrPng.value = "";
    await loadStatus();
  } finally {
    loading.value = false;
  }
}

async function regen() {
  const code = prompt("Enter current 2FA code");
  if (!code) return;
  loading.value = true;
  try {
    const res = await api().post(
      "/api/security/2fa/regenerate-recovery-codes",
      { code }
    );
    recovery.value = res.data.recovery_codes || [];
  } finally {
    loading.value = false;
  }
}

onMounted(loadStatus);
</script>
