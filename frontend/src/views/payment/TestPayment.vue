<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="text-lg font-semibold">Test Payment</div>
        <div class="text-sm text-muted-foreground">
          Generate a test payment QR code
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
                    class="w-64 h-64 flex items-center justify-center"
                  >
                    <img
                      :src="qrData.qr_png"
                      alt="KHQR Code"
                      class="w-full h-full object-contain"
                    />
                  </div>
                  <div
                    v-else-if="qrData.qr_svg"
                    class="w-64 h-64 flex items-center justify-center"
                    v-html="qrData.qr_svg"
                  ></div>
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
import { reactive, ref, computed } from "vue";
import { api } from "../../lib/api";
import Card from "../../components/ui/Card.vue";
import Input from "../../components/ui/Input.vue";
import Label from "../../components/ui/Label.vue";
import Button from "../../components/ui/Button.vue";

const loading = ref(false);
const qrData = ref(null);

const form = reactive({
  amount: "",
  order_id: "",
});

// Validate amount: must be a number >= 0.01
const isValidAmount = computed(() => {
  if (!form.amount || form.amount === "") return false;
  const num = parseFloat(form.amount);
  return !isNaN(num) && num >= 0.01;
});

async function generate() {
  if (!isValidAmount.value || !form.order_id) return;

  loading.value = true;
  qrData.value = null;

  try {
    // Convert amount to number before sending
    const payload = {
      amount: parseFloat(form.amount),
      order_id: form.order_id,
    };
    const res = await api().post("/api/payment/test", payload);
    qrData.value = res.data;
  } catch (e) {
    alert(e?.response?.data?.error || "Failed to generate QR code");
  } finally {
    loading.value = false;
  }
}
</script>
