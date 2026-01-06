<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="text-lg font-semibold">Merchant Information</div>
        <div class="text-sm text-muted-foreground">
          Configure merchant details separately for ABA and Bakong payment providers
        </div>
      </template>

      <div v-if="loading" class="text-sm text-muted-foreground">Loading...</div>

      <div v-else class="space-y-6">
        <!-- Provider Tabs -->
        <div class="flex gap-2 border-b border-border">
          <button
            @click="activeProvider = 'aba'"
            :class="{
              'border-b-2 border-primary text-primary': activeProvider === 'aba',
              'text-muted-foreground': activeProvider !== 'aba',
            }"
            class="px-4 py-2 text-sm font-medium transition-colors"
          >
            ABA Merchant
          </button>
          <button
            @click="activeProvider = 'bakong'"
            :class="{
              'border-b-2 border-primary text-primary': activeProvider === 'bakong',
              'text-muted-foreground': activeProvider !== 'bakong',
            }"
            class="px-4 py-2 text-sm font-medium transition-colors"
          >
            Bakong Merchant
          </button>
        </div>

        <!-- ABA Merchant Info -->
        <div v-if="activeProvider === 'aba'" class="space-y-4">
          <div class="rounded-lg border border-blue-200 bg-blue-50/50 p-4">
            <div class="text-sm font-medium text-blue-900 mb-2">ℹ️ About ABA Merchant ID</div>
            <div class="text-xs text-blue-800 space-y-1">
              <div>• Your ABA Merchant ID (MID) can be found in your ABA Merchant App</div>
              <div>• It appears on your QR codes as "MID: 126010616404196" (example)</div>
              <div>• You can register without documentation using "Preferred Business Name" option</div>
              <div>• Enter your MID in Payment Config → Bakong ID field (it's used for both providers)</div>
            </div>
          </div>

          <div class="space-y-2">
            <Label>Merchant City *</Label>
            <Input
              v-model="form.aba.merchant_city"
              placeholder="Phnom Penh"
            />
            <p class="text-xs text-muted-foreground">
              City name (max 15 characters). Used in QR code payload.
            </p>
          </div>

          <div class="space-y-2">
            <Label>Merchant Phone</Label>
            <Input
              v-model="form.aba.merchant_phone"
              placeholder="85512345678"
            />
            <p class="text-xs text-muted-foreground">
              Optional: Merchant contact phone number
            </p>
          </div>

          <div class="space-y-2">
            <Label>Merchant Email</Label>
            <Input
              v-model="form.aba.merchant_email"
              type="email"
              placeholder="merchant@example.com"
            />
            <p class="text-xs text-muted-foreground">
              Optional: Merchant contact email
            </p>
          </div>

          <div class="space-y-2">
            <Label>Merchant Address</Label>
            <Input
              v-model="form.aba.merchant_address"
              placeholder="Street address"
            />
            <p class="text-xs text-muted-foreground">
              Optional: Merchant physical address
            </p>
          </div>

          <div class="flex justify-end">
            <Button @click="save('aba')" :disabled="saving">
              {{ saving ? "Saving..." : "Save ABA Merchant Info" }}
            </Button>
          </div>
        </div>

        <!-- Bakong Merchant Info -->
        <div v-if="activeProvider === 'bakong'" class="space-y-4">
          <div class="rounded-lg border border-green-200 bg-green-50/50 p-4">
            <div class="text-sm font-medium text-green-900 mb-2">ℹ️ About Bakong ID</div>
            <div class="text-xs text-green-800 space-y-1">
              <div>• Your Bakong ID is your Bakong account identifier</div>
              <div>• It can be your phone number (e.g., 85512345678) or Bakong account ID</div>
              <div>• Enter it in Payment Config → Bakong ID field</div>
              <div>• Must be 1-25 characters for KHQR standard</div>
            </div>
          </div>

          <div class="space-y-2">
            <Label>Merchant City *</Label>
            <Input
              v-model="form.bakong.merchant_city"
              placeholder="Phnom Penh"
            />
            <p class="text-xs text-muted-foreground">
              City name (max 15 characters). Used in QR code payload.
            </p>
          </div>

          <div class="space-y-2">
            <Label>Merchant Phone</Label>
            <Input
              v-model="form.bakong.merchant_phone"
              placeholder="85512345678"
            />
            <p class="text-xs text-muted-foreground">
              Optional: Merchant contact phone number
            </p>
          </div>

          <div class="space-y-2">
            <Label>Merchant Email</Label>
            <Input
              v-model="form.bakong.merchant_email"
              type="email"
              placeholder="merchant@example.com"
            />
            <p class="text-xs text-muted-foreground">
              Optional: Merchant contact email
            </p>
          </div>

          <div class="space-y-2">
            <Label>Merchant Address</Label>
            <Input
              v-model="form.bakong.merchant_address"
              placeholder="Street address"
            />
            <p class="text-xs text-muted-foreground">
              Optional: Merchant physical address
            </p>
          </div>

          <div class="flex justify-end">
            <Button @click="save('bakong')" :disabled="saving">
              {{ saving ? "Saving..." : "Save Bakong Merchant Info" }}
            </Button>
          </div>
        </div>

        <div class="rounded-lg border border-border bg-muted/30 p-4">
          <div class="text-sm font-medium mb-2">Default Values</div>
          <div class="text-xs text-muted-foreground space-y-1">
            <div>• City: "Phnom Penh" (if not set)</div>
            <div>• Phone: Not included in QR code</div>
            <div>• Email: Not included in QR code</div>
            <div>• Address: Not included in QR code</div>
            <div class="mt-2 text-muted-foreground">
              Only Merchant City is embedded in the KHQR code. Other fields are stored for reference.
            </div>
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
const activeProvider = ref('aba'); // 'aba' or 'bakong'

const form = reactive({
  aba: {
    merchant_city: "Phnom Penh",
    merchant_phone: "",
    merchant_email: "",
    merchant_address: "",
  },
  bakong: {
    merchant_city: "Phnom Penh",
    merchant_phone: "",
    merchant_email: "",
    merchant_address: "",
  },
});

async function load() {
  loading.value = true;
  try {
    const res = await api().get("/api/payment/merchant-info");
    const info = res.data.merchant_info || {};
    
    // Load ABA merchant info
    if (info.aba) {
      form.aba.merchant_city = info.aba.merchant_city || "Phnom Penh";
      form.aba.merchant_phone = info.aba.merchant_phone || "";
      form.aba.merchant_email = info.aba.merchant_email || "";
      form.aba.merchant_address = info.aba.merchant_address || "";
    }
    
    // Load Bakong merchant info
    if (info.bakong) {
      form.bakong.merchant_city = info.bakong.merchant_city || "Phnom Penh";
      form.bakong.merchant_phone = info.bakong.merchant_phone || "";
      form.bakong.merchant_email = info.bakong.merchant_email || "";
      form.bakong.merchant_address = info.bakong.merchant_address || "";
    }
  } catch (e) {
    // If endpoint doesn't exist yet, use defaults
    console.log("Using default merchant info");
  } finally {
    loading.value = false;
  }
}

async function save(provider) {
  saving.value = true;
  try {
    const payload = {
      provider: provider,
      ...form[provider],
    };
    await api().post("/api/payment/merchant-info", payload);
    alert(`${provider.toUpperCase()} merchant information saved!`);
  } catch (e) {
    alert(e?.response?.data?.error || "Failed to save");
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>
