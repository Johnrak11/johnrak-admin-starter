<template>
  <div class="space-y-6">
    <Card>
      <template #header>
        <div class="text-lg font-semibold">API Access Tokens</div>
        <div class="text-sm text-muted-foreground">
          Generate secure API tokens for external applications and integrations
        </div>
      </template>

      <div class="space-y-4">
        <div class="rounded-lg border border-blue-200 bg-blue-50/50 p-4">
          <div class="text-sm font-medium text-blue-900 mb-2">🔐 About API Tokens</div>
          <div class="text-xs text-blue-800 space-y-1">
            <div>• Use API tokens to authenticate external applications that connect to your admin panel</div>
            <div>• Tokens are sent as: <code class="bg-white px-1 py-0.5 rounded">Authorization: Bearer YOUR_TOKEN</code></div>
            <div>• Set expiration dates for enhanced security</div>
            <div>• Revoke tokens anytime if they're compromised</div>
          </div>
        </div>

        <div class="space-y-2">
          <Label>Token Name</Label>
          <Input
            v-model="tokenForm.name"
            placeholder="My App Integration"
          />
          <p class="text-xs text-muted-foreground">
            Give your token a descriptive name (e.g., "Mobile App", "Python Script", "External Service")
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
            ⚠️ Token Generated - Save it now (shown only once)
          </div>
          <div class="font-mono text-sm break-all bg-background p-3 rounded border border-border mb-2">
            {{ newToken.token }}
          </div>
          <div class="text-xs text-muted-foreground mt-2 space-y-1">
            <div><b>Name:</b> {{ newToken.name }}</div>
            <div v-if="newToken.expires_at">
              <b>Expires:</b> {{ formatDate(newToken.expires_at) }}
            </div>
            <div v-else><b>Expiry:</b> Never expires</div>
          </div>
        </div>
      </div>
    </Card>

    <Card>
      <template #header>
        <div class="text-lg font-semibold">Active Tokens</div>
        <div class="text-sm text-muted-foreground">
          Manage your API access tokens
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
const tokens = ref([]);
const newToken = ref(null);

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
  loadTokens();
});
</script>
