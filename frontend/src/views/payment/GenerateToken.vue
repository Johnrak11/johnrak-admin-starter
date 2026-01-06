<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="text-lg font-semibold">Payment Webhook Secret</div>
        <div class="text-sm text-muted-foreground">
          Use this secret key in your Telegram bot for payment webhook authentication
        </div>
      </template>

      <div v-if="loadingWebhook" class="text-sm text-muted-foreground">
        Loading...
      </div>

      <div v-else class="space-y-4">
        <div
          v-if="webhookSecret"
          class="rounded-lg border border-blue-500/50 bg-blue-500/10 p-4"
        >
          <div class="text-sm font-medium text-blue-600 mb-2">
            🔐 Payment Webhook Secret
          </div>
          <div class="font-mono text-sm break-all bg-background p-3 rounded border border-border mb-2">
            {{ webhookSecret }}
          </div>
          <div class="text-xs text-muted-foreground space-y-1">
            <div>• Use this secret in your Python bot's <code>.env</code> file:</div>
            <div class="font-mono bg-muted p-2 rounded">
              PAYMENT_WEBHOOK_SECRET={{ webhookSecret }}
            </div>
            <div>• The bot will send this secret as <code>?key=SECRET</code> in webhook requests</div>
            <div>• Keep this secret secure and never share it publicly</div>
          </div>
        </div>

        <div
          v-else
          class="rounded-lg border border-yellow-500/50 bg-yellow-500/10 p-4"
        >
          <div class="text-sm font-medium text-yellow-600 mb-2">
            ⚠️ Webhook Secret Not Configured
          </div>
          <div class="text-xs text-muted-foreground">
            Go to <a href="/payment/config" class="text-primary underline cursor-pointer" @click.prevent="router.push('/payment/config')">Payment Config</a> and save your configuration to generate a webhook secret. The secret will appear here once configured.
          </div>
        </div>
      </div>
    </Card>

    <Card>
      <template #header>
        <div class="text-lg font-semibold">API Access Tokens</div>
        <div class="text-sm text-muted-foreground">
          Generate API tokens for external applications to connect and access your admin panel
        </div>
      </template>

      <div class="space-y-4">
        <div class="space-y-2">
          <Label>Token Name</Label>
          <Input
            v-model="tokenForm.name"
            placeholder="My App Integration"
          />
          <p class="text-xs text-muted-foreground">
            Give your token a descriptive name (e.g., "Mobile App", "Python Script", "Webhook Service")
          </p>
        </div>

        <div class="space-y-2">
          <Label>Expires In (Days)</Label>
          <Input
            v-model.number="tokenForm.expires_days"
            type="number"
            min="1"
            max="365"
            placeholder="30 (leave empty for no expiration)"
          />
          <p class="text-xs text-muted-foreground">
            Leave empty for tokens that never expire
          </p>
        </div>

        <Button @click="generate" :disabled="generating" class="w-full">
          {{ generating ? "Generating..." : "Generate Token" }}
        </Button>

        <div
          v-if="newToken"
          class="rounded-lg border border-green-500/50 bg-green-500/10 p-4"
        >
          <div class="text-sm font-medium text-green-600 mb-2">
            ⚠️ Token Generated (Save this - shown once)
          </div>
          <div class="font-mono text-sm break-all bg-background p-3 rounded border border-border mb-2">
            {{ newToken.token }}
          </div>
          <div class="text-xs text-muted-foreground">
            <div>Name: {{ newToken.name }}</div>
            <div v-if="newToken.expires_at">
              Expires: {{ formatDate(newToken.expires_at) }}
            </div>
            <div v-else>Never expires</div>
          </div>
        </div>
      </div>
    </Card>

    <Card>
      <template #header>
        <div class="text-lg font-semibold">Active API Tokens</div>
        <div class="text-sm text-muted-foreground">
          Manage and revoke API tokens for external applications
        </div>
      </template>

      <div v-if="loadingTokens" class="text-sm text-muted-foreground">
        Loading...
      </div>

      <div v-else-if="tokens.length === 0" class="text-center py-8">
        <p class="text-sm text-muted-foreground">No tokens generated yet</p>
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="token in tokens"
          :key="token.id"
          class="rounded-lg border border-border bg-muted/30 p-4"
        >
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <div class="text-sm font-medium">{{ token.name }}</div>
              <div class="text-xs text-muted-foreground space-y-1 mt-1">
                <div>
                  Status:
                  <span
                    :class="{
                      'text-green-600': token.is_valid,
                      'text-red-600': !token.is_valid,
                    }"
                  >
                    {{ token.is_valid ? "Active" : "Inactive/Expired" }}
                  </span>
                </div>
                <div v-if="token.last_used_at">
                  Last used: {{ formatDate(token.last_used_at) }}
                </div>
                <div v-else>Never used</div>
                <div v-if="token.expires_at">
                  Expires: {{ formatDate(token.expires_at) }}
                </div>
                <div>Created: {{ formatDate(token.created_at) }}</div>
              </div>
            </div>
            <Button
              v-if="token.is_valid"
              variant="danger"
              size="sm"
              @click="revoke(token)"
            >
              Revoke
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
import { formatDistanceToNow } from "date-fns";

const generating = ref(false);
const loadingTokens = ref(true);
const loadingWebhook = ref(true);
const tokens = ref([]);
const newToken = ref(null);
const webhookSecret = ref(null);

const tokenForm = reactive({
  name: "My App Integration",
  expires_days: null,
});

function formatDate(dateStr) {
  try {
    return formatDistanceToNow(new Date(dateStr), { addSuffix: true });
  } catch (e) {
    return dateStr;
  }
}

async function generate() {
  generating.value = true;
  try {
    const payload = {
      name: tokenForm.name || "My App Integration",
    };
    if (tokenForm.expires_days) {
      payload.expires_days = tokenForm.expires_days;
    }

    const res = await api().post("/api/payment/tokens", payload);
    newToken.value = res.data;
    tokenForm.name = "My App Integration";
    tokenForm.expires_days = null;
    await loadTokens();
  } catch (e) {
    alert(e?.response?.data?.error || "Failed to generate token");
  } finally {
    generating.value = false;
  }
}

async function loadWebhookSecret() {
  loadingWebhook.value = true;
  try {
    const res = await api().get("/api/payment/webhook-secret");
    if (res.data?.webhook_secret) {
      webhookSecret.value = res.data.webhook_secret;
    }
  } catch (e) {
    // Webhook secret might not be configured yet
    console.log("Webhook secret not available:", e);
  } finally {
    loadingWebhook.value = false;
  }
}

async function loadTokens() {
  loadingTokens.value = true;
  try {
    const res = await api().get("/api/payment/tokens");
    tokens.value = res.data.tokens || [];
  } finally {
    loadingTokens.value = false;
  }
}

async function revoke(token) {
  if (!confirm("Revoke this token? It will no longer work.")) return;
  try {
    await api().delete(`/api/payment/tokens/${token.id}`);
    await loadTokens();
  } catch (e) {
    alert("Failed to revoke token");
  }
}

onMounted(() => {
  loadWebhookSecret();
  loadTokens();
});
</script>
