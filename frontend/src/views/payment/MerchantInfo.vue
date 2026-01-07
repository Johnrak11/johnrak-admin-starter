<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="text-lg font-semibold">ABA Merchant Information</div>
        <div class="text-sm text-muted-foreground">
          Configure merchant details for ABA payment system
        </div>
      </template>

      <div v-if="loading" class="text-sm text-muted-foreground">Loading...</div>

      <div v-else class="space-y-6">
        <div class="rounded-lg border border-blue-200 bg-blue-50/50 p-4">
          <div class="text-sm font-medium text-blue-900 mb-2">ℹ️ About ABA Merchant</div>
          <div class="text-xs text-blue-800 space-y-1">
            <div>• These details are used to generate your KHQR payment codes</div>
            <div>• Merchant City is required and will be embedded in the QR code</div>
            <div>• Other fields are optional and stored for your reference</div>
            <div>• Make sure to configure your ABA Merchant ID in Payment Config first</div>
          </div>
        </div>

        <div class="space-y-4">
          <div class="space-y-2">
            <Label>Merchant City *</Label>
            <Input
              v-model="form.merchant_city"
              placeholder="Phnom Penh"
              maxlength="15"
            />
            <p class="text-xs text-muted-foreground">
              City name (max 15 characters). Used in ABA KHQR code payload.
            </p>
          </div>

          <div class="space-y-2">
            <Label>Merchant Phone</Label>
            <Input
              v-model="form.merchant_phone"
              placeholder="85512345678"
            />
            <p class="text-xs text-muted-foreground">
              Optional: Your contact phone number (for reference only)
            </p>
          </div>

          <div class="space-y-2">
            <Label>Merchant Email</Label>
            <Input
              v-model="form.merchant_email"
              type="email"
              placeholder="merchant@example.com"
            />
            <p class="text-xs text-muted-foreground">
              Optional: Your contact email (for reference only)
            </p>
          </div>

          <div class="space-y-2">
            <Label>Merchant Address</Label>
            <Input
              v-model="form.merchant_address"
              placeholder="Street address"
            />
            <p class="text-xs text-muted-foreground">
              Optional: Your business address (for reference only)
            </p>
          </div>

          <div class="flex justify-end">
            <Button @click="save" :disabled="saving">
              {{ saving ? "Saving..." : "Save Merchant Info" }}
            </Button>
          </div>
        </div>

        <div class="rounded-lg border border-border bg-muted/30 p-4">
          <div class="text-sm font-medium mb-2">📝 Note</div>
          <div class="text-xs text-muted-foreground space-y-1">
            <div>• Only "Merchant City" is embedded in the KHQR QR code</div>
            <div>• Phone, Email, and Address are stored for your records</div>
            <div>• City defaults to "Phnom Penh" if not set</div>
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

const loading = ref(true);
const saving = ref(false);

const form = reactive({
  merchant_city: "Phnom Penh",
  merchant_phone: "",
  merchant_email: "",
  merchant_address: "",
});

async function load() {
  loading.value = true;
  try {
    const res = await api().get("/api/payment/merchant-info");
    const info = res.data.merchant_info || {};
    
    form.merchant_city = info.merchant_city || "Phnom Penh";
    form.merchant_phone = info.merchant_phone || "";
    form.merchant_email = info.merchant_email || "";
    form.merchant_address = info.merchant_address || "";
  } catch (e) {
    console.log("Using default merchant info");
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  try {
    await api().post("/api/payment/merchant-info", form);
    alert("Merchant information saved successfully!");
  } catch (e) {
    alert(e?.response?.data?.error || "Failed to save");
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>
