<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="text-lg font-semibold">Bakong Payment Simulator</div>
        <div class="text-sm text-muted-foreground">
          Generate Bakong KHQR and simulate/check payment status
        </div>
      </template>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Left: Form -->
        <div class="space-y-4">
          <div class="space-y-2">
            <Label>Amount (USD) *</Label>
            <Input
              v-model="form.amount"
              type="number"
              step="0.01"
              min="0.01"
              placeholder="10.00"
            />
          </div>

          <!-- Order ID Removed (Auto-generated) -->

          <div class="space-y-2">
            <Label>Integration Token (Optional Override)</Label>
            <Input
              v-model="form.token"
              type="password"
              placeholder="Paste token here to override config"
              class="font-mono text-xs"
            />
            <p class="text-xs text-muted-foreground">
              Leave empty to use the token from configuration.
            </p>
          </div>

          <div
            class="flex items-center justify-between p-3 rounded-lg border border-border bg-muted/30"
          >
            <div class="flex-1">
              <Label class="cursor-pointer">Auto Check Status</Label>
              <p class="text-xs text-muted-foreground mt-1">
                Poll Bakong API for payment status
              </p>
            </div>
            <Switch v-model="form.autoCheck" />
          </div>

          <Button
            @click="generate"
            :disabled="loading || !isValidAmount"
            class="w-full bg-red-700 hover:bg-red-800"
          >
            {{ loading ? "Generating..." : "Generate KHQR" }}
          </Button>
        </div>

        <!-- Right: QR Code Display -->
        <div class="space-y-4">
          <div v-if="qrData" class="flex flex-col items-center justify-center">
            <!-- KHQR Style Payment Card (Bakong Style) -->
            <div
              class="w-full max-w-sm bg-white rounded-xl shadow-md overflow-hidden border border-gray-200"
            >
              <!-- Bakong Header Red -->
              <div
                class="bg-[#E31D1A] px-6 py-4 flex items-center justify-center relative"
              >
                <!-- Bakong Logo Placeholder or text -->
                <div class="text-white font-bold text-xl tracking-wider">
                  KHQR
                </div>
              </div>

              <div class="px-6 py-6 flex flex-col items-center">
                <div
                  class="text-gray-500 text-sm mb-1 uppercase tracking-wider"
                >
                  Total Amount
                </div>
                <div class="flex items-baseline gap-1 mb-6">
                  <span class="text-red-600 font-bold text-3xl">
                    {{ parseFloat(form.amount).toFixed(2) }}
                  </span>
                  <span class="text-gray-500 text-sm font-medium">USD</span>
                </div>

                <!-- QR Container -->
                <div
                  class="bg-white p-2 rounded-lg border-2 border-red-100 mb-4"
                >
                  <div
                    v-if="qrData.qr_png"
                    class="w-48 h-48 flex items-center justify-center"
                  >
                    <img
                      :src="qrData.qr_png"
                      alt="KHQR Code"
                      class="w-full h-full object-contain"
                    />
                  </div>
                  <div
                    v-else-if="qrData.qr_svg"
                    class="w-48 h-48 flex items-center justify-center"
                    v-html="qrData.qr_svg"
                  ></div>
                </div>

                <div
                  class="text-xs text-gray-400 font-mono mb-4 text-center break-all w-full px-4"
                >
                  {{ qrData.transaction?.order_id || "KHQR" }}
                </div>

                <!-- Status -->
                <div
                  v-if="paymentStatus"
                  class="w-full text-center py-2 px-4 rounded-full text-sm font-bold animate-in fade-in zoom-in duration-300"
                  :class="{
                    'bg-green-100 text-green-700': paymentStatus === 'paid',
                    'bg-yellow-100 text-yellow-700':
                      paymentStatus === 'pending',
                    'bg-red-100 text-red-700': paymentStatus === 'unknown',
                  }"
                >
                  <span v-if="paymentStatus === 'paid'"
                    >✅ Payment Successful</span
                  >
                  <span v-else-if="paymentStatus === 'pending'"
                    >Checking Payment...</span
                  >
                  <span v-else>❌ Status Unknown</span>
                </div>
              </div>
            </div>

            <!-- Manual Check Button -->
            <div class="mt-4 w-full max-w-sm" v-if="qrData.md5">
              <Button
                @click="checkStatusManual"
                variant="outline"
                class="w-full"
                :disabled="checking"
              >
                {{ checking ? "Checking..." : "Check Status Now" }}
              </Button>
            </div>
          </div>

          <div
            v-else
            class="flex flex-col items-center justify-center h-full min-h-[300px] rounded-lg border border-dashed border-border bg-muted/10 text-center p-8"
          >
            <div class="text-4xl mb-4 opacity-50">📲</div>
            <h3 class="font-medium text-lg">Payment Simulator</h3>
            <p class="text-sm text-muted-foreground mt-2 max-w-xs">
              Enter an amount and click generate to create a clear KHQR code for
              testing.
            </p>
          </div>
        </div>
      </div>
    </Card>
  </div>
</template>

<script setup>
import { reactive, ref, computed, onUnmounted, onMounted } from "vue";
import { api } from "../../lib/api";
import Card from "../../components/ui/Card.vue";
import Input from "../../components/ui/Input.vue";
import Label from "../../components/ui/Label.vue";
import Button from "../../components/ui/Button.vue";
import Switch from "../../components/ui/Switch.vue";

const loading = ref(false);
const checking = ref(false);
const qrData = ref(null);
const paymentStatus = ref(null);
const pollingInterval = ref(null);

const form = reactive({
  amount: "0.01",
  autoCheck: true,
  token: "",
});

const isValidAmount = computed(() => {
  const num = parseFloat(form.amount);
  return !isNaN(num) && num > 0;
});

// Load config to prefill token if available
onMounted(async () => {
  try {
    const res = await api().get("/api/payment/config");
    if (res.data.config.bakong_token) {
      form.token = res.data.config.bakong_token;
    }
  } catch (e) {
    // ignore
  }
});

async function generate() {
  if (!isValidAmount.value) return;

  loading.value = true;
  qrData.value = null;
  paymentStatus.value = null;
  stopPolling();

  try {
    const res = await api().post("/api/payment/test", {
      amount: form.amount,
    });
    qrData.value = res.data;

    if (form.autoCheck && qrData.value.md5) {
      startPolling();
    }
  } catch (e) {
    alert("Failed to generate: " + (e.response?.data?.error || e.message));
  } finally {
    loading.value = false;
  }
}

async function checkStatusManual() {
  if (!qrData.value?.md5) return;
  checking.value = true;
  try {
    await checkStatus();
  } finally {
    checking.value = false;
  }
}

async function checkStatus() {
  if (!qrData.value?.md5) return;

  try {
    const res = await api().post("/api/payment/bakong/check-status", {
      md5: qrData.value.md5,
      token: form.token || undefined, // Pass token if overridden
    });

    const status = res.data.status; // 'paid', 'pending', 'unknown'
    paymentStatus.value = status;

    if (status === "paid") {
      stopPolling();
      // alert("SUCCESS: Payment Received!");
      // Removed alert for smoother UI flow with the badge
    }
  } catch (e) {
    console.error("Check status failed", e);
  }
}

function startPolling() {
  stopPolling();
  paymentStatus.value = "pending";
  pollingInterval.value = setInterval(checkStatus, 3000); // Check every 3s
  checkStatus(); // Check immediately
}

function stopPolling() {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value);
    pollingInterval.value = null;
  }
}

onUnmounted(() => {
  stopPolling();
});
</script>
