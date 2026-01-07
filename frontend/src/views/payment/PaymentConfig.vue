<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="text-lg font-semibold">ABA Merchant Configuration</div>
        <div class="text-sm text-muted-foreground">
          Configure your ABA Merchant payment settings
        </div>
      </template>

      <div v-if="loading" class="text-sm text-muted-foreground">Loading...</div>

      <div v-else class="space-y-6">
        <div class="rounded-lg border border-blue-200 bg-blue-50/50 p-4">
          <div class="text-sm font-medium text-blue-900 mb-2">🏦 How to get your ABA Merchant ID</div>
          <div class="text-xs text-blue-800 space-y-1">
            <div>1. Register in ABA Merchant App using "Preferred Business Name" (no docs required)</div>
            <div>2. After registration, your MID appears on generated QR codes</div>
            <div>3. Copy your 15-digit MID (e.g., 126010616404196) and paste below</div>
          </div>
        </div>

        <div class="space-y-4">
          <div class="space-y-2">
            <Label>ABA Merchant ID (MID) *</Label>
            <Input
              v-model="form.aba_merchant_id"
              placeholder="126010616404196"
              maxlength="25"
            />
            <p class="text-xs text-muted-foreground">
              Your 15-digit ABA Merchant ID from ABA Merchant App (found on your QR codes as "MID: ...")
            </p>
          </div>

          <div class="space-y-2">
            <Label>Merchant Name</Label>
            <Input
              v-model="form.merchant_name"
              placeholder="Your Business Name"
              maxlength="25"
            />
            <p class="text-xs text-muted-foreground">
              Business name (max 25 characters, will be shown in QR code)
            </p>
          </div>

          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm font-medium">Enable Payments</div>
              <div class="text-xs text-muted-foreground">
                Allow payment QR code generation
              </div>
            </div>
            <Switch v-model="form.enabled" />
          </div>

          <div class="flex justify-end">
            <Button @click="save" :disabled="saving">
              {{ saving ? "Saving..." : "Save Configuration" }}
            </Button>
          </div>
        </div>

        <div class="rounded-lg border border-border bg-muted/30 p-4">
          <div class="text-sm font-medium mb-2">✓ Next Steps</div>
          <div class="text-xs text-muted-foreground space-y-1">
            <div>• Configure your merchant details in Merchant Info</div>
            <div>• Generate payment QR codes in Generate Payment QR</div>
            <div>• Track transactions in Transactions</div>
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

const form = reactive({
  aba_merchant_id: "",
  merchant_name: "",
  enabled: true,
});

async function load() {
  loading.value = true;
  try {
    const res = await api().get("/api/payment/config");
    const config = res.data.config;
    form.aba_merchant_id = config.aba_merchant_id || "";
    form.merchant_name = config.merchant_name || "";
    form.enabled = config.enabled ?? true;
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  try {
    await api().post("/api/payment/config", form);
    alert("ABA Merchant configuration saved successfully!");
  } catch (e) {
    alert(e?.response?.data?.error || "Failed to save");
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>
