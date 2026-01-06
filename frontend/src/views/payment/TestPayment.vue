<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="text-lg font-semibold">Generate Payment QR</div>
        <div class="text-sm text-muted-foreground">
          Create a payment QR code for your customers to scan and pay
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

          <div class="space-y-2">
            <Label>Order ID *</Label>
            <Input v-model="form.order_id" placeholder="ORDER-12345" />
            <p class="text-xs text-muted-foreground">
              This will be embedded in the QR code remark field
            </p>
          </div>

          <div class="flex items-center justify-between p-3 rounded-lg border border-border bg-muted/30">
            <div class="flex-1">
              <Label class="cursor-pointer">Scan and Pay Now!</Label>
              <p class="text-xs text-muted-foreground mt-1">
                Automatically check payment status until paid (max 2 minutes)
              </p>
            </div>
            <Switch v-model="form.autoCheck" />
          </div>

          <Button
            @click="generate"
            :disabled="loading || !isValidAmount || !form.order_id"
            class="w-full"
          >
            {{ loading ? "Generating..." : "Generate QR Code" }}
          </Button>
        </div>

        <!-- Right: QR Code Display -->
        <div class="space-y-4">
          <div v-if="qrData" class="flex flex-col items-center justify-center">
            <!-- KHQR Style Payment Card -->
            <div
              class="w-full max-w-sm bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-200"
            >
              <!-- Red Header with KHOR branding -->
              <div class="relative bg-red-600 px-6 py-4">
                <div class="flex items-center justify-center">
                  <div class="text-white font-bold text-2xl tracking-wide">
                    KHQR
                  </div>
                </div>
                <!-- Small triangular cutout effect on top right -->
                <div
                  class="absolute top-0 right-0 w-0 h-0 border-l-[20px] border-l-transparent border-t-[20px] border-t-red-700"
                ></div>
              </div>

              <!-- White Body -->
              <div class="px-6 py-5">
                <!-- Merchant/Recipient Name -->
                <div class="text-gray-800 font-medium text-base mb-3">
                  {{ form.order_id || "Payment" }}
                </div>

                <!-- Amount Display -->
                <div class="flex items-baseline gap-2 mb-4">
                  <span class="text-gray-900 font-bold text-3xl">
                    ${{
                      parseFloat(form.amount).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })
                    }}
                  </span>
                  <span class="text-gray-600 text-sm font-normal">USD</span>
                </div>

                <!-- Dashed Separator -->
                <div class="border-t border-dashed border-gray-300 my-4"></div>

                <!-- QR Code Container -->
                <div
                  class="flex justify-center items-center bg-white p-4 rounded-lg"
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

                <!-- Payment Status Check -->
                <div v-if="pollingActive && currentTransaction" class="mt-4 text-center">
                  <div class="flex items-center justify-center gap-2 mb-2">
                    <div
                      class="w-2 h-2 rounded-full animate-pulse"
                      :class="{
                        'bg-green-500': paymentStatus === 'paid',
                        'bg-yellow-500': paymentStatus === 'pending',
                        'bg-gray-500': !pollingActive,
                      }"
                    ></div>
                    <span class="text-sm font-medium">
                      {{ paymentStatus === 'paid' ? 'Payment Received!' : 'Waiting for payment...' }}
                    </span>
                  </div>
                  <div v-if="paymentStatus === 'pending'" class="text-xs text-muted-foreground space-y-1">
                    <div>Checking payment status...</div>
                    <div>Time remaining: {{ Math.floor(countdown / 60) }}:{{ String(countdown % 60).padStart(2, '0') }}</div>
                  </div>
                </div>

                <!-- Warning Text -->
                <div class="mt-4 text-center">
                  <p class="text-xs text-gray-500 font-medium">
                    Use this QR to pay only one time
                  </p>
                </div>
              </div>
            </div>

            <!-- Action Button -->
            <div class="mt-4 w-full max-w-sm">
              <a
                v-if="qrData.bakong_link"
                :href="qrData.bakong_link"
                target="_blank"
                class="block w-full"
              >
                <Button variant="secondary" class="w-full">
                  Open in Bakong App
                </Button>
              </a>
            </div>
          </div>

          <div
            v-else
            class="flex flex-col items-center justify-center p-12 rounded-lg border border-dashed border-border text-center"
          >
            <div class="text-4xl mb-4">📱</div>
            <p class="text-sm text-muted-foreground">
              Enter amount and order ID, then click "Generate QR Code"
            </p>
          </div>
        </div>
      </div>

      <div
        v-if="qrData && qrData.khqr_string"
        class="mt-6 rounded-lg border border-border bg-muted/30 p-4"
      >
        <div class="text-xs font-medium text-muted-foreground mb-2">
          KHQR String (for debugging)
        </div>
        <div
          class="font-mono text-xs break-all bg-background p-2 rounded border border-border"
        >
          {{ qrData.khqr_string }}
        </div>
      </div>
    </Card>
  </div>
</template>

<script setup>
import { reactive, ref, computed, onUnmounted } from "vue";
import { api } from "../../lib/api";
import Card from "../../components/ui/Card.vue";
import Input from "../../components/ui/Input.vue";
import Label from "../../components/ui/Label.vue";
import Button from "../../components/ui/Button.vue";
import Switch from "../../components/ui/Switch.vue";

const loading = ref(false);
const qrData = ref(null);
const currentTransaction = ref(null);
const paymentStatus = ref(null);
const pollingActive = ref(false);
const countdown = ref(120); // 2 minutes in seconds
const startTime = ref(null);
let pollingInterval = null;
let countdownInterval = null;

const form = reactive({
  amount: "",
  order_id: "",
  autoCheck: false, // Checkbox for "Pay Now" option
});

// Validate amount: must be a number >= 0.01
const isValidAmount = computed(() => {
  if (!form.amount || form.amount === "") return false;
  const num = parseFloat(form.amount);
  return !isNaN(num) && num >= 0.01;
});

function startPolling(transactionId) {
  // Stop any existing polling
  stopPolling();
  
  currentTransaction.value = transactionId;
  paymentStatus.value = 'pending';
  pollingActive.value = true;
  countdown.value = 120; // Reset to 2 minutes
  startTime.value = Date.now();

  // Countdown timer (counts down from 120 to 0)
  countdownInterval = setInterval(() => {
    const elapsed = Math.floor((Date.now() - startTime.value) / 1000);
    const remaining = Math.max(0, 120 - elapsed);
    countdown.value = remaining;
    
    if (remaining <= 0) {
      stopPolling();
      alert('⏱️ Time limit reached (2 minutes). Payment check stopped.');
    }
  }, 1000);

  // Check payment status continuously every 3 seconds until paid or 2 minutes elapsed
  pollingInterval = setInterval(async () => {
    const elapsed = Math.floor((Date.now() - startTime.value) / 1000);
    if (elapsed >= 120) {
      stopPolling();
      return;
    }
    
    await checkPaymentStatus(transactionId);
    
    // If payment is still pending, continue checking
    if (paymentStatus.value === 'paid') {
      stopPolling();
    }
  }, 3000); // Check every 3 seconds

  // Check immediately
  checkPaymentStatus(transactionId);
}

function stopPolling() {
  if (pollingInterval) {
    clearInterval(pollingInterval);
    pollingInterval = null;
  }
  if (countdownInterval) {
    clearInterval(countdownInterval);
    countdownInterval = null;
  }
  pollingActive.value = false;
}

async function checkPaymentStatus(transactionId) {
  try {
    const res = await api().get(`/api/payment/transactions/${transactionId}`);
    const transaction = res.data.transaction;
    
    if (transaction.status === 'paid') {
      paymentStatus.value = 'paid';
      stopPolling();
      // Show success message
      alert('✅ Payment received! The transaction has been completed.');
    } else {
      paymentStatus.value = transaction.status;
    }
  } catch (e) {
    console.error('Failed to check payment status:', e);
  }
}

async function generate() {
  if (!isValidAmount.value || !form.order_id) return;

  loading.value = true;
  qrData.value = null;
  stopPolling();

  try {
    // Convert amount to number before sending
    const payload = {
      amount: parseFloat(form.amount),
      order_id: form.order_id,
    };
    const res = await api().post("/api/payment/test", payload);
    qrData.value = res.data;
    
    // Start polling only if "Pay Now" checkbox is checked
    if (form.autoCheck && res.data.transaction?.id) {
      startPolling(res.data.transaction.id);
    }
  } catch (e) {
    alert(e?.response?.data?.error || "Failed to generate QR code");
  } finally {
    loading.value = false;
  }
}

// Cleanup on unmount
onUnmounted(() => {
  stopPolling();
});
</script>
