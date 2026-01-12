<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="text-lg font-semibold">Bakong Payment Configuration</div>
        <div class="text-sm text-muted-foreground">
          Configure your Bakong KHQR payment settings
        </div>
      </template>

      <div v-if="loading" class="text-sm text-muted-foreground">Loading...</div>

      <div v-else class="space-y-6">
        <div class="rounded-lg border border-blue-200 bg-blue-50/50 p-4">
          <div class="text-sm font-medium text-blue-900 mb-2">
            ℹ️ Bakong Integration
          </div>
          <div class="text-xs text-blue-800 space-y-1">
            <div>
              1. Enter your Bakong Account ID (e.g., username@devb or
              username@bakong)
            </div>
            <div>
              2. Use "Renew / Generate Token" to get your integration token via
              email
            </div>
            <div>
              3. Merchant Name & City are required for generic KHQR generation
              (Tag 59/60)
            </div>
          </div>
        </div>

        <div class="space-y-4">
          <!-- Bakong ID -->
          <div class="space-y-2">
            <Label>Bakong Account ID *</Label>
            <Input
              v-model="form.aba_merchant_id"
              placeholder="username@bakong"
              maxlength="100"
            />
            <p class="text-xs text-muted-foreground">
              Your Bakong account ID (Tag 29). Example: username@devb (SIT) or
              username@bakong.
            </p>
          </div>

          <!-- Merchant Name -->
          <div class="space-y-2">
            <Label>Merchant Name *</Label>
            <Input
              v-model="form.merchant_name"
              placeholder="My Shop"
              maxlength="25"
            />
            <p class="text-xs text-muted-foreground">
              Required for KHQR (Tag 59). This name appears when users scan the
              QR code.
            </p>
          </div>

          <!-- Merchant City -->
          <div class="space-y-2">
            <Label>Merchant City</Label>
            <Input
              v-model="form.merchant_city"
              placeholder="Phnom Penh"
              maxlength="15"
            />
            <p class="text-xs text-muted-foreground">
              City (Tag 60). Defaults to Phnom Penh if empty.
            </p>
          </div>

          <!-- Token Renewal Section -->
          <div class="space-y-2 pt-4 border-t">
            <Label>Integration Token Management</Label>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="space-y-2">
                <Label class="text-xs">Registered Email</Label>
                <div class="flex gap-2">
                  <Input
                    v-model="form.merchant_email"
                    placeholder="email@example.com"
                  />
                  <Button
                    variant="outline"
                    @click="renewToken"
                    :disabled="renewing || !form.merchant_email"
                  >
                    {{ renewing ? "Generating..." : "Renew / Generate Token" }}
                  </Button>
                </div>
                <p class="text-xs text-muted-foreground">
                  Enter your Bakong registered email to generate a new token.
                </p>
              </div>

              <div class="space-y-2">
                <Label class="text-xs">Current Token</Label>
                <Input
                  v-model="form.bakong_token"
                  type="password"
                  placeholder="Paste your token here..."
                  class="font-mono text-sm"
                />
                <p class="text-xs text-muted-foreground">
                  Last generated:
                  {{ form.bakong_token ? "Token Present" : "None" }}
                </p>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-between mt-4 border-t pt-4">
            <div>
              <div class="text-sm font-medium">Enable Payments</div>
              <div class="text-xs text-muted-foreground">
                Allow payment QR code generation
              </div>
            </div>
            <Switch v-model="form.enabled" />
          </div>

          <div class="flex justify-end pt-2">
            <Button @click="save" :disabled="saving">
              {{ saving ? "Saving..." : "Save Configuration" }}
            </Button>
          </div>
        </div>
      </div>
    </Card>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from "vue";
import { api } from "../../lib/api";
import Card from "../../components/ui/Card.vue";
import Input from "../../components/ui/Input.vue";
import Label from "../../components/ui/Label.vue";
import Button from "../../components/ui/Button.vue";
import Switch from "../../components/ui/Switch.vue";

const loading = ref(true);
const saving = ref(false);
const renewing = ref(false);

const form = reactive({
  aba_merchant_id: "",
  merchant_name: "",
  merchant_city: "Phnom Penh",
  merchant_email: "",
  enabled: true,
  // Provider is implicitly Bakong
  bakong_token: "",
});

async function load() {
  loading.value = true;
  try {
    const res = await api().get("/api/payment/config");
    const config = res.data.config;
    form.aba_merchant_id = config.aba_merchant_id || "";
    form.merchant_name = config.merchant_name || "";
    form.merchant_city = config.merchant_city || "Phnom Penh";
    form.merchant_email = config.merchant_email || "";
    form.enabled = config.enabled ?? true;
    form.bakong_token = config.bakong_token || "";
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  try {
    // We force provider=bakong backend side mostly, but can send it too
    await api().post("/api/payment/config", form);
    alert("Configuration saved successfully!");
  } catch (e) {
    alert(e?.response?.data?.error || "Failed to save");
  } finally {
    saving.value = false;
  }
}

async function renewToken() {
  if (!form.merchant_email) return;

  renewing.value = true;
  try {
    const res = await api().post("/api/payment/bakong/renew-token", {
      email: form.merchant_email,
    });

    // Update token in form
    if (res.data.token) {
      form.bakong_token = res.data.token;
      alert("Token generated successfully! Don't forget to save.");
    }
  } catch (e) {
    alert(e?.response?.data?.error || "Failed to renew token");
  } finally {
    renewing.value = false;
  }
}

onMounted(load);
</script>
