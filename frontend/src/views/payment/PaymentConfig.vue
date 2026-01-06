<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="text-lg font-semibold">Payment Gateway Configuration</div>
        <div class="text-sm text-muted-foreground">
          Configure your Bakong payment settings
        </div>
      </template>

      <div v-if="loading" class="text-sm text-muted-foreground">Loading...</div>

      <div v-else class="space-y-4">
        <div class="space-y-2">
          <Label>Provider</Label>
          <select
            v-model="form.provider"
            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          >
            <option value="bakong">Bakong</option>
            <option value="aba">ABA</option>
          </select>
        </div>

        <div class="space-y-2">
          <Label>Bakong ID *</Label>
          <Input
            v-model="form.bakong_id"
            placeholder="Your Bakong account ID"
          />
          <p class="text-xs text-muted-foreground">
            Your Bakong merchant account identifier
          </p>
        </div>

        <div class="space-y-2">
          <Label>Merchant Name</Label>
          <Input
            v-model="form.merchant_name"
            placeholder="Your Business Name"
          />
        </div>

        <div class="flex items-center justify-between">
          <div>
            <div class="text-sm font-medium">Enable Payments</div>
            <div class="text-xs text-muted-foreground">
              Allow payment processing
            </div>
          </div>
          <Switch v-model="form.enabled" />
        </div>

        <div
          v-if="webhookSecret"
          class="rounded-lg border border-border bg-muted/30 p-4"
        >
          <div class="text-xs font-medium text-muted-foreground mb-2">
            Webhook Secret (Save this - shown once)
          </div>
          <div class="font-mono text-sm break-all bg-background p-2 rounded border border-border">
            {{ webhookSecret }}
          </div>
          <p class="text-xs text-muted-foreground mt-2">
            Use this secret in your Python bot: <code>?key=SECRET</code>
          </p>
        </div>

        <div class="flex justify-end">
          <Button @click="save" :disabled="saving">
            {{ saving ? "Saving..." : "Save Configuration" }}
          </Button>
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
const webhookSecret = ref(null);

const form = reactive({
  provider: "bakong",
  bakong_id: "",
  merchant_name: "",
  enabled: true,
});

async function load() {
  loading.value = true;
  try {
    const res = await api().get("/api/payment/config");
    const config = res.data.config;
    form.provider = config.provider || "bakong";
    form.bakong_id = config.bakong_id || "";
    form.merchant_name = config.merchant_name || "";
    form.enabled = config.enabled ?? true;
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  try {
    const res = await api().post("/api/payment/config", form);
    if (res.data.webhook_secret) {
      webhookSecret.value = res.data.webhook_secret;
    }
    alert("Configuration saved!");
  } catch (e) {
    alert(e?.response?.data?.error || "Failed to save");
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>
